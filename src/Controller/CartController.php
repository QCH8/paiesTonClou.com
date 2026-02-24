<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier', name: 'app_cart_')]
final class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->requireUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cart = $this->getOrCreateOpenCart($user);

        $totalHtCents = 0;
        $totalTtcCents = 0;
        foreach ($cart->getCartItem() as $item) {
            $lineHt = $item->getQuantity() * $item->getUnitPriceHTSnapshot();
            $lineTtc = (int) round($lineHt * (1 + $item->getVatRateSnapshot()));
            $totalHtCents += $lineHt;
            $totalTtcCents += $lineTtc;
        }

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'total_ht_cents' => $totalHtCents,
            'total_ttc_cents' => $totalTtcCents,
        ]);
    }

    #[Route('/add/{id}', name: 'add', methods: ['POST'])]
    public function add(int $id, Request $request): RedirectResponse
    {
        $user = $this->requireUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $variant = $this->productVariantRepository->find($id);
        if (!$variant || !$variant->isActive()) {
            $this->addFlash('error', 'Variante introuvable ou inactive.');
            return $this->redirectToRoute('shop_products_index');
        }

        if (!$this->isCsrfTokenValid('cart_add_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            $slug = $variant->getProduct()?->getSlug();
            if ($slug) {
                return $this->redirectToRoute('shop_products_show', ['slug' => $slug]);
            }

            return $this->redirectToRoute('shop_products_index');
        }

        $quantity = max(1, $request->request->getInt('quantity', 1));
        $cart = $this->getOrCreateOpenCart($user);

        $item = $this->cartItemRepository->findOneByCartAndVariant($cart, $variant);
        if (!$item) {
            $item = (new CartItem())
                ->setCart($cart)
                ->setProductVariant($variant)
                ->setQuantity(0)
                ->setUnitPriceHTSnapshot($variant->getPriceHT())
                ->setVatRateSnapshot($variant->getVatRate());
            $this->em->persist($item);
        }

        $item->setQuantity($item->getQuantity() + $quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $this->em->flush();

        $this->addFlash('success', 'Produit ajouté au panier.');

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/item/{id}/update', name: 'item_update', methods: ['POST'])]
    public function updateItem(int $id, Request $request): RedirectResponse
    {
        $user = $this->requireUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $item = $this->cartItemRepository->find($id);
        if (!$item || $item->getCart()?->getUser()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ligne panier introuvable.');
            return $this->redirectToRoute('app_cart_index');
        }

        if (!$this->isCsrfTokenValid('cart_item_update_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $quantity = $request->request->getInt('quantity', 1);
        if ($quantity <= 0) {
            $this->em->remove($item);
            $this->addFlash('success', 'Ligne retirée du panier.');
        } else {
            $item->setQuantity($quantity);
            $this->addFlash('success', 'Quantité mise a jour.');
        }

        $item->getCart()?->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $this->em->flush();

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/item/{id}/remove', name: 'item_remove', methods: ['POST'])]
    public function removeItem(int $id, Request $request): RedirectResponse
    {
        $user = $this->requireUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $item = $this->cartItemRepository->find($id);
        if (!$item || $item->getCart()?->getUser()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Ligne panier introuvable.');
            return $this->redirectToRoute('app_cart_index');
        }

        if (!$this->isCsrfTokenValid('cart_item_remove_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $cart = $item->getCart();
        $this->em->remove($item);
        if ($cart) {
            $cart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        }
        $this->em->flush();

        $this->addFlash('success', 'Produit retire du panier.');
        return $this->redirectToRoute('app_cart_index');
    }

    private function requireUser(): ?User
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function getOrCreateOpenCart(User $user): Cart
    {
        $cart = $this->cartRepository->findOpenCartForUser($user);
        if ($cart) {
            return $cart;
        }

        $cart = (new Cart())
            ->setUser($user)
            ->setStatus('open')
            ->setCurrency('EUR');

        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }
}
