<?php

namespace App\Services;

use App\Entity\ShippingAddress;
use Stripe\Checkout\Session;

class StripeCheckoutSessionDataMapper
{
    public function extractShippingAddress(Session $checkoutSession): ?ShippingAddress
    {
        $shippingDetails = $checkoutSession->shipping_details ?? null;
        $customerDetails = $checkoutSession->customer_details ?? null;

        $address = $shippingDetails->address ?? $customerDetails->address ?? null;
        $fullName = $shippingDetails->name ?? $customerDetails->name ?? null;
        $phone = $shippingDetails->phone ?? $customerDetails->phone ?? null;

        $line1 = $address->line1 ?? null;
        $line2 = $address->line2 ?? null;
        $city = $address->city ?? null;
        $postalCode = $address->postal_code ?? null;
        $country = $address->country ?? null;

        if (!$fullName || !$line1 || !$city || !$postalCode || !$country) {
            return null;
        }

        return (new ShippingAddress())
            ->setFullname((string) $fullName)
            ->setLine1((string) $line1)
            ->setLine2($line2 ? (string) $line2 : null)
            ->setCity((string) $city)
            ->setPostalCode((string) $postalCode)
            ->setCountry((string) $country)
            ->setPhone($phone ? (string) $phone : null);
    }
}
