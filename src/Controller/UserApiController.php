<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserApiController extends AbstractController
{
    #[Route('/user-loader', name: 'user_loader')]
    public function userLoader(Request $request, JWTTokenManagerInterface $jwtManager, UserRepository $userRepository): Response
    {
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            return $this->redirect('/login'); // ou une 403
        }

        try {
            $decoded = $jwtManager->parse($token);
            $user = $userRepository->findOneBy(['email' => $decoded['username']]);

            if (!$user || !in_array('ROLE_USER', $user->getRoles())) {
                return new Response('Accès refusé', 403);
            }

            return $this->render('user_api/indexUser.html.twig', [
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return new Response('Token invalide', 403);
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


