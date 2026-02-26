<?php

namespace App\Controller;

use App\Entity\StripeEvent;
use App\Repository\StripeEventRepository;
use App\Services\PaymentConfirmationEmail;
use App\Services\StripeCheckoutCompletedProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        StripeCheckoutCompletedProcessor $stripeCheckoutCompletedProcessor,
        PaymentConfirmationEmail $paymentConfirmationEmail,
        LoggerInterface $logger,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] string $webhookSecret
    ): Response
    {
        // Stripe posts the raw event payload and a signature header.
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature) {
            return new JsonResponse(['error' => 'Missing Stripe-Signature header'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Reject payloads with invalid signature to prevent spoofed events.
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid webhook payload or signature'], Response::HTTP_BAD_REQUEST);
        }

        // Idempotence guard: Stripe can retry events, we store each id only once.
        $existingEvent = $stripeEventRepository->findOneBy(['stripeEventId' => $event->id]);
        if ($existingEvent) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        // Persist full event trace for audit/debug.
        $stripeEvent = (new StripeEvent())
            ->setStripeEventId($event->id)
            ->setType($event->type)
            ->setPayload(\json_decode($payload, true))
            ->setProcessingStatus('received');

        // Business transition happens when checkout is completed.
        $orderToNotify = null;
        if ('checkout.session.completed' === $event->type) {
            /** @var \Stripe\Checkout\Session $checkoutSession */
            $checkoutSession = $event->data->object;
            // Toute la logique métier Stripe est déléguée au service dédié.
            $orderToNotify = $stripeCheckoutCompletedProcessor->process($checkoutSession, $stripeEvent, $event->id, $em);
        }

        // Mark the stored event as processed before commit.
        $stripeEvent->setProcessingStatus('processed');
        $stripeEvent->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        $em->persist($stripeEvent);
        $em->flush();

        if ($orderToNotify) {
            try {
                $paymentConfirmationEmail->sendOrderPaidConfirmation($orderToNotify);
            } catch (\Throwable $e) {
                $logger->error('Payment confirmation email failed', [
                    'order_id' => $orderToNotify->getId(),
                    'order_number' => $orderToNotify->getNumber(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse(['received' => true]);
    }
}
