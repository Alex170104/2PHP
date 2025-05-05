<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;



class UserApiController extends AbstractController
{
    #[Route('/user-loader', name: 'user_loader')]
    public function userLoader(Request $request, JWTTokenManagerInterface $jwtManager, UserRepository $userRepository): Response
    {
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            return $this->redirect('/login');
        }

        try {
            $decoded = $jwtManager->parse($token);
            $user = $userRepository->findOneBy(['email' => $decoded['username']]);

            if (!$user || !in_array('ROLE_USER', $user->getRoles())) {
                return new Response('Accès refusé', 403);
            }

            // ✅ Redirection vers /user-loader/{id}
            return $this->redirectToRoute('user_loader_id', ['id' => $user->getId()]);
        } catch (\Exception $e) {
            return new Response('Token invalide', 403);
        }
    }

    #[Route('/user-loader/{id}', name: 'user_loader_id')]
    public function userLoaderWithId(
        int $id,
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository
    ): Response {
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            return $this->redirect('/login');
        }

        try {
            $decoded = $jwtManager->parse($token);
            $userFromToken = $userRepository->findOneBy(['email' => $decoded['username']]);
            $targetUser = $userRepository->find($id);

            if (!$userFromToken || !$targetUser) {
                return $this->render('error.html.twig', ['message' => 'Utilisateur introuvable']);
            }

            // Si l'utilisateur authentifié n'est pas admin et essaie de voir un autre utilisateur
            if (
                $userFromToken->getId() !== $targetUser->getId() &&
                !in_array('ROLE_ADMIN', $userFromToken->getRoles())
            ) {
                return $this->render('error.html.twig', ['message' => 'Accès refusé']);
            }

            return $this->render('user_api/indexUser.html.twig', [
                'user' => $targetUser
            ]);
        } catch (\Exception $e) {
            return $this->render('error.html.twig', ['message' => 'Token invalide']);
        }
    }






    #[Route('/Api/user', name: 'user_secure')]
    public function secureUser(Request $request, JWTTokenManagerInterface $jwtManager, UserRepository $userRepository): Response
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new Response('Token manquant', 403);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = $jwtManager->parse($token);
            $email = $decoded['username'];
            $user = $userRepository->findOneBy(['email' => $email]);

            if (!$user || !in_array('ROLE_USER', $user->getRoles())) {
                return new Response('Non autorisé', 403);
            }

            return $this->render('user_api/indexUser.html.twig', [
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return new Response('Token invalide', 403);
        }
    }



}


