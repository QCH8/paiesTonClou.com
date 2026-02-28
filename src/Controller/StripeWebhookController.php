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
        // Raw Stripe event payload + signature header
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature) {
            return new JsonResponse(['error' => 'En-tete Stripe-Signature manquant'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Rejection SE payloads invalide pour empecher les events factices / falsifiés
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Payload webhook ou signature invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Idempotence : Stripe peut réessayer les events, stockage de chaque id une fois seulement
        $existingEvent = $stripeEventRepository->findOneBy(['stripeEventId' => $event->id]);
        if ($existingEvent) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        // Stockage des SE entiers pour audit/debug
        $stripeEvent = (new StripeEvent())
            ->setStripeEventId($event->id)
            ->setType($event->type)
            ->setPayload(\json_decode($payload, true))
            ->setProcessingStatus('received');

        // Business is Business quand le checkout est complété
        $orderToNotify = null;
        if ('checkout.session.completed' === $event->type) {
            /** @var \Stripe\Checkout\Session $checkoutSession */
            $checkoutSession = $event->data->object;
            // Appel service pour logique métier Stripe
            $orderToNotify = $stripeCheckoutCompletedProcessor->process($checkoutSession, $stripeEvent, $event->id, $em);
        }

        // Passage de l'event stocké en "processed" avant persist
        $stripeEvent->setProcessingStatus('processed');
        $stripeEvent->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        $em->persist($stripeEvent);
        $em->flush();

        if ($orderToNotify) {
            try {
                $paymentConfirmationEmail->sendOrderPaidConfirmation($orderToNotify);
            } catch (\Throwable $e) {
                $logger->error('Echec de l\'envoi de l\'email de confirmation de paiement', [
                    'order_id' => $orderToNotify->getId(),
                    'order_number' => $orderToNotify->getNumber(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse(['received' => true]);
    }
}
