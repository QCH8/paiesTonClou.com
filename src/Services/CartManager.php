<?php

namespace App\Services;

use App\Entity\Cart;
use App\Entity\User;
use App\Repository\CartItemRepository;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartManager
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getConsolidatedOpenCart(User $user): ?Cart
    {
        $openCarts = $this->cartRepository->findOpenCartsForUser($user);
        if ([] === $openCarts) {
            return null;
        }

        return $this->consolidateOpenCarts($openCarts);
    }

    public function getOrCreateOpenCart(User $user): Cart
    {
        $openCart = $this->getConsolidatedOpenCart($user);
        if ($openCart) {
            return $openCart;
        }

        $cart = (new Cart())
            ->setUser($user)
            ->setStatus('open')
            ->setCurrency('EUR');

        $this->em->persist($cart);
        $this->em->flush();

        return $cart;
    }

    /**
     * @param list<Cart> $openCarts
     */
    private function consolidateOpenCarts(array $openCarts): Cart
    {
        $targetCart = $openCarts[0];
        if (\count($openCarts) === 1) {
            return $targetCart;
        }

        foreach (\array_slice($openCarts, 1) as $sourceCart) {
            foreach ($sourceCart->getCartItem()->toArray() as $sourceItem) {
                $variant = $sourceItem->getProductVariant();
                if (!$variant) {
                    $this->em->remove($sourceItem);
                    continue;
                }

                $targetItem = $this->cartItemRepository->findOneByCartAndVariant($targetCart, $variant);
                if ($targetItem) {
                    $targetItem->setQuantity($targetItem->getQuantity() + $sourceItem->getQuantity());
                    $this->em->remove($sourceItem);
                    continue;
                }

                $sourceItem->setCart($targetCart);
            }

            $sourceCart->setStatus('merged');
            $sourceCart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        }

        $targetCart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $this->em->flush();

        return $targetCart;
    }
}
