<?php

namespace App\Services;

use App\Entity\Cart;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripePayment
{

    public function __construct(readonly private string $stripeSecretKey)
    {
        Stripe::setApiKey($this->stripeSecretKey);
        # Stripe::setApiVersion('2026-01-28.clover');
    }

    public function startPayment(Cart $cart, string $successUrl, string $cancelUrl): Session
    {
        $lineItems = [];
        foreach ($cart->getCartItem() as $item) {
            $productVariant = $item->getProductVariant();
            if (!$productVariant) {
                throw new RuntimeException('Cart item has no product variant.');
            }

            $unitPrice = $item->getUnitPriceHTSnapshot();
            if ($unitPrice === null) {
                throw new RuntimeException('Cart item has no unit price.');
            }

            $quantity = $item->getQuantity();
            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => $productVariant->getName()],
                    'unit_amount' => (int) $unitPrice,
                ],
                'quantity' => $quantity,
            ];
        }

        if ([] === $lineItems) {
            throw new RuntimeException('Cannot start a Stripe checkout session with an empty cart.');
        }

        return Session::create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'billing_address_collection' => 'required',
            'shipping_address_collection' => [
                'allowed_countries' => ['FR'],
            ],
            'metadata' => ['cart_id' => (string) $cart->getId()],
        ]);
    }
}
