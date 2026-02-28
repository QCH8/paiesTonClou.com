<?php

namespace App\Services;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\ProductVariant;
use App\Entity\ShippingAddress;
use App\Entity\StripeEvent;
use App\Repository\CartRepository;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeCheckoutCompletedProcessor
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly OrderRepository $orderRepository,
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly CartRepository $cartRepository,
        private readonly StripeCheckoutSessionDataMapper $stripeCheckoutSessionDataMapper,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')] private readonly string $stripeSecretKey,
    ) {
    }

    public function process(
        Session $checkoutSession,
        StripeEvent $stripeEvent,
        string $eventId,
        EntityManagerInterface $em,
    ): ?Order {
        // Le webhook "checkout.session.completed" peut être rejoué
        // Avec session Stripe => retrouver/MàJ l'état local sans créer de doublon fonctionnel
        $checkoutSessionId = $checkoutSession->id ?? null;
        if (!$checkoutSessionId) {
            return null;
        }

        // 1) Tentative de repartir d'un payment déjà existant
        $payment = $this->paymentRepository->findOneBy(['stripeCheckoutSessionId' => $checkoutSessionId]);
        // 2) Chargement des line_items Stripe
        $lineItems = $this->fetchCheckoutLineItems($checkoutSessionId);
        // 3) Tentative de relier l'event à un panier via metadata/client_reference_id
        $cartId = $this->resolveCartId($checkoutSession);
        $cart = $cartId ? $this->cartRepository->find($cartId) : null;

        // Résolution de la commande locale dans l'ordre le plus fiable
        $order = $payment?->getOrder();
        if (!$order && $cart) {
            $order = $this->orderRepository->findOneBy(['cart' => $cart]);
        }

        // Si la commande n'existe pas, création depuis session Stripe.
        if (!$order) {
            $order = $this->createOrderFromCheckoutSession($checkoutSession, $cart);
            $em->persist($order);
        }

        // Alimentation des lignes: priorité au snapshot panier local, sinon Stripe line_items.
        if ($order->getOrderItems()->isEmpty()) {
            if ($cart && !$cart->getCartItem()->isEmpty()) {
                $this->addOrderItemsFromCart($order, $cart);
            } else {
                $this->addOrderItemsFromStripeLineItems($order, $lineItems);
            }
        }

        // Recalcule des totaux et adresse pour garder la commande cohérente.
        $this->refreshOrderTotals($order, $checkoutSession);
        $this->refreshOrderShippingAddress($order, $checkoutSession);

        $customerEmail = $this->resolveCustomerEmail($checkoutSession, $cart?->getUser()?->getEmail());
        $order
            ->setCustomerEmail($customerEmail)
            ->setCurrency($this->resolveSessionCurrency($checkoutSession, $order->getCurrency() ?? 'EUR'));

        // Notification email uniquement lors de la transition vers "paid".
        $orderToNotify = null;
        $wasPaid = 'paid' === $order->getStatus();
        if ('paid' === ($checkoutSession->payment_status ?? null)) {
            $order->setStatus('paid');
            if (null === $order->getPaidAt()) {
                $order->setPaidAt($this->nowParis());
            }
            if (!$wasPaid) {
                $orderToNotify = $order;
            }
        } elseif (null === $order->getStatus()) {
            $order->setStatus('pending');
        }

        // Création ou synchronisation du paiement associé à la session Stripe.
        if (!$payment) {
            $payment = $this->createPaymentFromCheckoutSession($checkoutSession, $order, $eventId);
            $em->persist($payment);
        } else {
            $payment
                ->setOrder($order)
                ->setStatus($this->mapPaymentStatus($checkoutSession->payment_status ?? null))
                ->setCurrency($this->resolveSessionCurrency($checkoutSession, $payment->getCurrency() ?? 'EUR'))
                ->setAmountTTC($this->resolveSessionAmountTtc($checkoutSession, $order->getTotalTTC() ?? 0))
                ->setIdempotencyKey($eventId);
        }

        // Link explicite des objets pour la traçabilité.
        $payment->setStripePaymentIntentId($this->extractPaymentIntentId($checkoutSession));
        $stripeEvent->setOrder($order);

        // Une fois la commande confirmée, clear et fermeture du panier source.
        if ($cart) {
            foreach ($cart->getCartItem()->toArray() as $cartItem) {
                $em->remove($cartItem);
            }

            $cart->setStatus('converted');
            $cart->setUpdatedAt($this->nowParis());
        }

        return $orderToNotify;
    }

    private function fetchCheckoutLineItems(string $checkoutSessionId): array
    {
        try {
            // Appel API Stripe secondaire pour récupérer les détails produit/price.
            $stripeClient = new StripeClient($this->stripeSecretKey);
            $lineItems = $stripeClient->checkout->sessions->allLineItems($checkoutSessionId, [
                'limit' => 100,
                'expand' => ['data.price.product'],
            ]);

            return $lineItems->data ?? [];
        } catch (ApiErrorException $e) {
            // Pas de block du webhook si Stripe refuse cet appel annexe.
            $this->logger->warning('Impossible de recuperer les lignes de la session Stripe Checkout', [
                'checkout_session_id' => $checkoutSessionId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function resolveCartId(Session $checkoutSession): ?int
    {
        // Compat: lecture de metadata.cart_id, puis client_reference_id.
        $metadataCartId = $this->readMetadataValue($checkoutSession->metadata ?? null, 'cart_id');
        if ($metadataCartId && ctype_digit($metadataCartId) && (int) $metadataCartId > 0) {
            return (int) $metadataCartId;
        }

        $clientReferenceId = $checkoutSession->client_reference_id ?? null;
        if (\is_string($clientReferenceId) && ctype_digit($clientReferenceId) && (int) $clientReferenceId > 0) {
            return (int) $clientReferenceId;
        }

        return null;
    }

    private function createOrderFromCheckoutSession(Session $checkoutSession, ?Cart $cart): Order
    {
        $shippingAddress = $this->stripeCheckoutSessionDataMapper->extractShippingAddress($checkoutSession)
            ?? $this->fallbackShippingAddress($checkoutSession);

        return (new Order())
            ->setNumber($this->generateOrderNumber())
            ->setStatus('pending')
            ->setCustomerEmail($this->resolveCustomerEmail($checkoutSession, $cart?->getUser()?->getEmail()))
            ->setCurrency($this->resolveSessionCurrency($checkoutSession, $cart?->getCurrency() ?? 'EUR'))
            ->setTotalHT(0)
            ->setTotalVAT(0)
            ->setTotalTTC($this->resolveSessionAmountTtc($checkoutSession, 0))
            ->setShippingAddress($shippingAddress)
            ->setUser($cart?->getUser())
            ->setCart($cart);
    }

    private function createPaymentFromCheckoutSession(Session $checkoutSession, Order $order, string $eventId): Payment
    {
        return (new Payment())
            ->setOrder($order)
            ->setProvider('stripe')
            ->setStatus($this->mapPaymentStatus($checkoutSession->payment_status ?? null))
            ->setStripeCheckoutSessionId((string) $checkoutSession->id)
            ->setStripePaymentIntentId($this->extractPaymentIntentId($checkoutSession))
            ->setAmountTTC($this->resolveSessionAmountTtc($checkoutSession, $order->getTotalTTC() ?? 0))
            ->setCurrency($this->resolveSessionCurrency($checkoutSession, $order->getCurrency() ?? 'EUR'))
            ->setIdempotencyKey($eventId);
    }

    private function addOrderItemsFromCart(Order $order, Cart $cart): void
    {
        foreach ($cart->getCartItem() as $cartItem) {
            $variant = $cartItem->getProductVariant();
            if (!$variant) {
                continue;
            }

            $quantity = max(1, (int) $cartItem->getQuantity());
            $unitPriceHT = max(0, (int) ($cartItem->getUnitPriceHTSnapshot() ?? $variant->getPriceHT() ?? 0));
            $vatRate = max(0.0, (float) ($cartItem->getVatRateSnapshot() ?? $variant->getVatRate() ?? 0.2));

            $orderItem = (new OrderItem())
                ->setOrder($order)
                ->setProductVariant($variant)
                ->setQuantity($quantity)
                ->setUnitPriceHTSnapshot($unitPriceHT)
                ->setVatRateSnapshot($vatRate)
                ->setNameSnapshot($variant->getName() ?? 'Produit')
                ->setStockKeepingUnitSnapshot($variant->getStockKeepingUnit() ?? 'SKU-UNKNOWN');

            $order->addOrderItem($orderItem);
        }
    }

    private function addOrderItemsFromStripeLineItems(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $quantity = max(1, (int) ($lineItem->quantity ?? 1));
            $variant = $this->resolveVariantFromLineItem($lineItem);

            $vatRate = max(0.0, (float) ($variant?->getVatRate() ?? 0.2));
            $unitAmountTtc = $this->resolveLineItemUnitAmountTtc($lineItem, $quantity);
            $unitPriceHT = $variant?->getPriceHT();
            if (null === $unitPriceHT) {
                // Si la variante locale est introuvable, on reconstruit un HT approximatif depuis TTC.
                $denominator = 1 + $vatRate;
                if ($denominator <= 0) {
                    $denominator = 1;
                }
                $unitPriceHT = null === $unitAmountTtc ? 0 : (int) round($unitAmountTtc / $denominator);
            }

            $nameSnapshot = $variant?->getName() ?? (string) ($lineItem->description ?? 'Produit Stripe');
            if ('' === trim($nameSnapshot)) {
                $nameSnapshot = 'Produit Stripe';
            }

            $skuSnapshot = $variant?->getStockKeepingUnit()
                ?? $this->resolveSkuFromLineItem($lineItem)
                ?? 'STRIPE-' . (string) ($lineItem->price->id ?? uniqid('price_', false));

            $orderItem = (new OrderItem())
                ->setOrder($order)
                ->setProductVariant($variant)
                ->setQuantity($quantity)
                ->setUnitPriceHTSnapshot(max(0, (int) $unitPriceHT))
                ->setVatRateSnapshot($vatRate)
                ->setNameSnapshot($nameSnapshot)
                ->setStockKeepingUnitSnapshot($skuSnapshot);

            $order->addOrderItem($orderItem);
        }
    }

    private function refreshOrderTotals(Order $order, Session $checkoutSession): void
    {
        $totalHT = 0;
        $totalVAT = 0;

        foreach ($order->getOrderItems() as $orderItem) {
            $quantity = max(1, (int) $orderItem->getQuantity());
            $unitPriceHT = max(0, (int) $orderItem->getUnitPriceHTSnapshot());
            $vatRate = max(0.0, (float) $orderItem->getVatRateSnapshot());

            $lineHT = $unitPriceHT * $quantity;
            $lineVAT = (int) round($lineHT * $vatRate);

            $totalHT += $lineHT;
            $totalVAT += $lineVAT;
        }

        $totalTTC = $totalHT + $totalVAT;
        $stripeAmountTotal = $this->resolveSessionAmountTtc($checkoutSession, $totalTTC);
        if ($stripeAmountTotal > 0) {
            $totalTTC = $stripeAmountTotal;
            if ($totalHT > 0) {
                $totalVAT = max(0, $totalTTC - $totalHT);
            }
        }

        $order
            ->setTotalHT($totalHT)
            ->setTotalVAT($totalVAT)
            ->setTotalTTC($totalTTC);
    }

    private function refreshOrderShippingAddress(Order $order, Session $checkoutSession): void
    {
        $shippingAddress = $this->stripeCheckoutSessionDataMapper->extractShippingAddress($checkoutSession);
        if ($shippingAddress) {
            $order->setShippingAddress($shippingAddress);
            return;
        }

        try {
            $order->getShippingAddress();
        } catch (\LogicException) {
            $order->setShippingAddress($this->fallbackShippingAddress($checkoutSession));
        }
    }

    private function resolveVariantFromLineItem(mixed $lineItem): ?ProductVariant
    {
        $price = $lineItem->price ?? null;
        $product = $price->product ?? null;

        // Résolution 1: metadata explicite envoyée au checkout.
        $variantId = $this->readMetadataValue($product->metadata ?? null, 'product_variant_id')
            ?? $this->readMetadataValue($price->metadata ?? null, 'product_variant_id');

        if ($variantId && ctype_digit($variantId)) {
            $variant = $this->productVariantRepository->find((int) $variantId);
            if ($variant) {
                return $variant;
            }
        }

        // Résolution 2: fallback SKU.
        $sku = $this->readMetadataValue($product->metadata ?? null, 'sku')
            ?? $this->readMetadataValue($price->metadata ?? null, 'sku');

        if ($sku) {
            return $this->productVariantRepository->findOneBy(['stockKeepingUnit' => $sku]);
        }

        return null;
    }

    private function resolveSkuFromLineItem(mixed $lineItem): ?string
    {
        $price = $lineItem->price ?? null;
        $product = $price->product ?? null;

        return $this->readMetadataValue($product->metadata ?? null, 'sku')
            ?? $this->readMetadataValue($price->metadata ?? null, 'sku');
    }

    private function resolveLineItemUnitAmountTtc(mixed $lineItem, int $quantity): ?int
    {
        $unitAmount = $lineItem->price->unit_amount ?? null;
        if (\is_numeric($unitAmount)) {
            return max(0, (int) $unitAmount);
        }

        $amountSubtotal = $lineItem->amount_subtotal ?? null;
        if (\is_numeric($amountSubtotal) && $quantity > 0) {
            return max(0, (int) round((int) $amountSubtotal / $quantity));
        }

        $amountTotal = $lineItem->amount_total ?? null;
        if (\is_numeric($amountTotal) && $quantity > 0) {
            return max(0, (int) round((int) $amountTotal / $quantity));
        }

        return null;
    }

    private function readMetadataValue(mixed $metadata, string $key): ?string
    {
        if (\is_array($metadata) && isset($metadata[$key])) {
            return (string) $metadata[$key];
        }

        if (\is_object($metadata)) {
            if (isset($metadata->$key)) {
                return (string) $metadata->$key;
            }

            if ($metadata instanceof \ArrayAccess && isset($metadata[$key])) {
                return (string) $metadata[$key];
            }
        }

        return null;
    }

    private function resolveCustomerEmail(Session $checkoutSession, ?string $fallbackEmail = null): string
    {
        $email = $checkoutSession->customer_details->email
            ?? $checkoutSession->customer_email
            ?? $fallbackEmail;

        if (\is_string($email) && '' !== trim($email)) {
            return $email;
        }

        return 'unknown@stripe.local';
    }

    private function resolveSessionCurrency(Session $checkoutSession, string $fallback): string
    {
        $currency = $checkoutSession->currency ?? null;
        if (\is_string($currency) && '' !== trim($currency)) {
            return strtoupper($currency);
        }

        return strtoupper($fallback);
    }

    private function resolveSessionAmountTtc(Session $checkoutSession, int $fallback): int
    {
        $amountTotal = $checkoutSession->amount_total ?? null;
        if (\is_numeric($amountTotal) && (int) $amountTotal >= 0) {
            return (int) $amountTotal;
        }

        return max(0, $fallback);
    }

    private function extractPaymentIntentId(Session $checkoutSession): ?string
    {
        $paymentIntent = $checkoutSession->payment_intent ?? null;
        if (\is_string($paymentIntent) && '' !== trim($paymentIntent)) {
            return $paymentIntent;
        }

        if (\is_object($paymentIntent) && isset($paymentIntent->id) && \is_string($paymentIntent->id)) {
            return $paymentIntent->id;
        }

        return null;
    }

    private function mapPaymentStatus(?string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'succeeded',
            'unpaid' => 'pending',
            'no_payment_required' => 'no_payment_required',
            default => 'pending',
        };
    }

    private function fallbackShippingAddress(Session $checkoutSession): ShippingAddress
    {
        $customerDetails = $checkoutSession->customer_details ?? null;
        $address = $customerDetails->address ?? null;

        $fullName = (string) ($customerDetails->name ?? $checkoutSession->customer_email ?? 'Client Stripe');
        $line1 = (string) ($address->line1 ?? 'Adresse inconnue');
        $line2 = isset($address->line2) ? (string) $address->line2 : null;
        $city = (string) ($address->city ?? 'N/A');
        $postalCode = (string) ($address->postal_code ?? '00000');
        $country = (string) ($address->country ?? 'FR');
        $phone = isset($customerDetails->phone) ? (string) $customerDetails->phone : null;

        return (new ShippingAddress())
            ->setFullname($fullName)
            ->setLine1($line1)
            ->setLine2($line2)
            ->setCity($city)
            ->setPostalCode($postalCode)
            ->setCountry($country)
            ->setPhone($phone);
    }

    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = sprintf(
                'ORD-%s-%s',
                $this->nowParis()->format('YmdHis'),
                strtoupper(substr(md5(uniqid((string) $attempt, true)), 0, 6))
            );

            if (!$this->orderRepository->findOneBy(['number' => $candidate])) {
                return $candidate;
            }
        }

        return sprintf('ORD-%s-%s', $this->nowParis()->format('YmdHis'), strtoupper(uniqid('F', false)));
    }

    private function nowParis(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }
}
