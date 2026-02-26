<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use App\Services\StripePayment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/stripe/checkout/session', name: 'app_stripe_checkout_session_')]
final class StripeCheckoutSessionController extends AbstractController
{

    #[Route('/payer', name: 'payment', methods: ['POST'])]
    public function payer(
        Request $request,
        StripePayment $payment,
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ): Response
    {
        // Seul un User Authentifié peut passer en Checkout.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // Protection antiCsrf pour les initialisations de checkout depuis des sites externes.
        if (!$this->isCsrfTokenValid('stripe_checkout_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Création de panier checkout et consolidation si des paniers ouverts "legacy" existent.
        $cart = $this->resolveCheckoutCart($user, $cartRepository, $cartItemRepository, $em);
        if (null === $cart || $cart->getCartItem()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Stripe requiert des URLs absolues pour son fonctionnement.
        $successUrl = $this->generateUrl('app_stripe_checkout_session_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('app_cart_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $session = $payment->startPayment($cart, $successUrl, $cancelUrl);
        } catch (\Throwable $e) {
            $logger->error('Stripe checkout session creation failed', [
                'message' => $e->getMessage(),
                'cart_id' => $cart->getId(),
                'user_id' => $user->getId(),
            ]);
            $this->addFlash('error', 'Impossible de créer la session de paiement Stripe.');

            return $this->redirectToRoute('app_cart_index');
        }

        // Logs pour support/debug si remontées pb Utilisateurs.
        $logger->info('Stripe checkout session created', [
            'session_id' => $session->id,
            'session_url' => $session->url,
            'cart_id' => $cart->getId(),
            'user_id' => $user->getId(),
        ]);

        return $this->redirect($session->url, 303);
    }

    #[Route('/success', name: 'checkout_success', methods: ['GET'])]
    public function success(): Response
    {
        return $this->render('stripe_checkout_session/success.html.twig');
    }

    private function resolveCheckoutCart(
        User $user,
        CartRepository $cartRepository,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em
    ): ?Cart {
        // cible : le cart le plus récent. Si anciens cart existants then consolidation
        $openCarts = $cartRepository->findOpenCartsForUser($user);
        if ([] === $openCarts) {
            return null;
        }

        $targetCart = $openCarts[0];
        if (count($openCarts) === 1) {
            return $targetCart;
        }

        foreach (array_slice($openCarts, 1) as $sourceCart) {
            foreach ($sourceCart->getCartItem()->toArray() as $sourceItem) {
                $variant = $sourceItem->getProductVariant();
                if (!$variant) {
                    // Si pas de variant (= incorrect data) => remove la ligne.
                    $em->remove($sourceItem);
                    continue;
                }

                $targetItem = $cartItemRepository->findOneByCartAndVariant($targetCart, $variant);
                if ($targetItem) {
                    // Déjà la même variante dans le cart : consolidation de la quantité.
                    $targetItem->setQuantity($targetItem->getQuantity() + $sourceItem->getQuantity());
                    $em->remove($sourceItem);
                    continue;
                }

                // Envoi de l'item vers le Cart actuel.
                $sourceItem->setCart($targetCart);
            }

            // On garde l'historique, pas de suppression de Cart.
            $sourceCart->setStatus('merged');
            $sourceCart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        }

        $targetCart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $em->flush();

        return $targetCart;
    }
}
