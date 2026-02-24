<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\CartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_item_count', [$this, 'cartItemCount']),
        ];
    }

    public function cartItemCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        $cart = $this->cartRepository->findOpenCartForUser($user);
        if (!$cart) {
            return 0;
        }

        $count = 0;
        foreach ($cart->getCartItem() as $item) {
            $count += max(0, (int) $item->getQuantity());
        }

        return $count;
    }
}

