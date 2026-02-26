<?php

namespace App\Controller;

use App\Entity\StripeEvent;
use App\Repository\CartRepository;
use App\Repository\PaymentRepository;
use App\Repository\StripeEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        StripeEventRepository $stripeEventRepository,
        PaymentRepository $paymentRepository,
        CartRepository $cartRepository,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] string $webhookSecret
    ): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature) {
            return new JsonResponse(['error' => 'Missing Stripe-Signature header'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid webhook payload or signature'], Response::HTTP_BAD_REQUEST);
        }

        $existingEvent = $stripeEventRepository->findOneBy(['stripeEventId' => $event->id]);
        if ($existingEvent) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        $stripeEvent = (new StripeEvent())
            ->setStripeEventId($event->id)
            ->setType($event->type)
            ->setPayload(\json_decode($payload, true))
            ->setProcessingStatus('received');

        if ('checkout.session.completed' === $event->type) {
            /** @var \Stripe\Checkout\Session $checkoutSession */
            $checkoutSession = $event->data->object;
            $checkoutSessionId = $checkoutSession->id ?? null;

            if ($checkoutSessionId) {
                $payment = $paymentRepository->findOneBy(['stripeCheckoutSessionId' => $checkoutSessionId]);
                if ($payment) {
                    $payment->setStatus('succeeded');
                    $payment->setStripePaymentIntentId($checkoutSession->payment_intent ?? null);

                    $order = $payment->getOrder();
                    if ($order && 'paid' !== $order->getStatus()) {
                        $order->setStatus('paid');
                        $order->setPaidAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
                    }
                }
            }

            $cartId = isset($checkoutSession->metadata['cart_id']) ? (int) $checkoutSession->metadata['cart_id'] : null;
            if ($cartId) {
                $cart = $cartRepository->find($cartId);
                if ($cart) {
                    foreach ($cart->getCartItem()->toArray() as $cartItem) {
                        $em->remove($cartItem);
                    }

                    $cart->setStatus('converted');
                    $cart->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
                }
            }
        }

        $stripeEvent->setProcessingStatus('processed');
        $stripeEvent->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        $em->persist($stripeEvent);
        $em->flush();

        return new JsonResponse(['received' => true]);
    }
}
