<?php

namespace App\Controller;

use App\Services\AuthenticatedUserProvider;
use App\Services\CartManager;
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
        CartManager $cartManager,
        AuthenticatedUserProvider $authenticatedUserProvider,
        LoggerInterface $logger
    ): Response {
        // Seuls les Users connectés peuvent initier un paiement.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $authenticatedUserProvider->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Protection CSRF sur le démarrage du checkout Stripe.
        if (!$this->isCsrfTokenValid('stripe_checkout_start', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        // Consolidation des éventuels paniers ouverts avant envoi à Stripe.
        $cart = $cartManager->getConsolidatedOpenCart($user);
        if ( $cart === null || $cart->getCartItem()->isEmpty()) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart_index');
        }

        // URLs absolues requises par Stripe pour redirection succès ou annulation.
        $successUrl = $this->generateUrl('app_stripe_checkout_session_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('app_cart_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            // Création de la session Checkout côté serveur.
            $session = $payment->startPayment($cart, $successUrl, $cancelUrl);
        } catch (\Throwable $e) {
            // Log le contexte technique + renvoi de l'User au panier.
            $logger->error('Echec de la creation de la session Stripe Checkout', [
                'message' => $e->getMessage(),
                'cart_id' => $cart->getId(),
                'user_id' => $user->getId(),
            ]);
            $this->addFlash('error', 'Impossible de créer la session de paiement Stripe.');
            return $this->redirectToRoute('app_cart_index');
        }

        $logger->info('Stripe checkout session created', [
            'session_id' => $session->id,
            'session_url' => $session->url,
                'cart_id' => $cart->getId(),
                'user_id' => $user->getId(),
        ]);
        // Redirection HTTP 303 vers la page de paiement hébergée par Stripe.
        return $this->redirect($session->url, 303);
    }

    #[Route('/success', name: 'checkout_success', methods: ['GET'])]
    public function success(): Response
    {
        return $this->render('stripe_checkout_session/success.html.twig');
    }
}
