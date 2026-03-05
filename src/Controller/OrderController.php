<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Services\AuthenticatedUserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-commandes', name: 'app_order_')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        OrderRepository $orderRepository,
        AuthenticatedUserProvider $authenticatedUserProvider,
    ): Response {
        // Page reservee aux utilisateurs connectes.
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $authenticatedUserProvider->getAuthenticatedUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findUserOrdersWithDetails($user);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }
}
