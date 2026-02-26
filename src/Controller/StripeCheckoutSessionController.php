<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Services\StripePayment;
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
        LoggerInterface $logger
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('stripe_checkout_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        $cart = $cartRepository->findOpenCartForUser($user);
        if (!$cart || $cart->getCartItem()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

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
            $this->addFlash('error', 'Impossible de creer la session de paiement Stripe.');

            return $this->redirectToRoute('app_cart_index');
        }

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
}
