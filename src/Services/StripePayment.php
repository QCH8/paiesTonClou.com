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
        // Server-side secret key used for all Stripe API calls.
        Stripe::setApiKey($this->stripeSecretKey);
        # Stripe::setApiVersion('2026-01-28.clover');
    }

    public function startPayment(Cart $cart, string $successUrl, string $cancelUrl): Session
    {
        // Convert internal cart lines into Stripe Checkout line_items.
        $lineItems = [];
        foreach ($cart->getCartItem() as $item) {
            $productVariant = $item->getProductVariant();
            if (!$productVariant) {
                throw new RuntimeException('Cart item has no product variant.');
            }

            // Prices are stored as HT snapshot + VAT snapshot in DB.
            $unitPriceHt = $item->getUnitPriceHTSnapshot();
            $vatRate = $item->getVatRateSnapshot();
            if ($unitPriceHt === null || $vatRate === null ) {
                throw new RuntimeException('Cart item has no unit price or VAT rate.');
            }

            // Stripe expects integer cents: send TTC as unit_amount.
            $unitPrice = (int) round($unitPriceHt * (1 + $vatRate));

            $quantity = $item->getQuantity();
            if ($quantity === null || $quantity <= 0) {
                // Ignore invalid quantities instead of sending broken lines to Stripe.
                continue;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => $productVariant->getName()],
                    'unit_amount' => $unitPrice,
                ],
                'quantity' => $quantity,
            ];
        }

        if ([] === $lineItems) {
            throw new RuntimeException('Cannot start a Stripe checkout session with an empty cart.');
        }

        // Metadata links Stripe events back to our local cart.
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
