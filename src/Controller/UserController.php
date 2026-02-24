<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/mon_profil', name: 'app_user_')]
final class UserController extends AbstractController
{
    #[Route('', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur invalide.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserEditType::class, $user, [
            'locked' => true,
        ]);

        return $this->render('user\\my_profile.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/edit', name: 'edit', methods: ['POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur invalide.');
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserEditType::class, $user, [
            'locked' => false,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() && $form->isValid()){
            $this->addFlash('error', 'Invalid form please check data.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword) && $plainPassword !== '') {
                $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Modifications enregistrees.');
            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('user\\my_profile.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Utilisateur invalide.');
            return $this->redirectToRoute('app_login');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_account', $csrfToken)) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_profile');
        }

        $confirmation = (string) $request->request->get('delete_confirmation', '');
        if ($confirmation !== 'SUPPRIMER') {
            $this->addFlash('error', 'Tapez SUPPRIMER pour confirmer la suppression du compte.');
            return $this->redirectToRoute('app_user_profile');
        }

        $em->remove($user);
        $em->flush();

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', 'Votre compte a ete supprime.');

        return $this->redirectToRoute('shop_products_index');
    }

    #[Route('/{id}/create', name: 'create')]
    public function create(): Response
    {
        return $this->render('user\\my_profile.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route('/{id}/email-verification', name: 'email_verification')]
    public function resendEmailVerification(): Response
    {
        return $this->render('user\\my_profile.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }
}
