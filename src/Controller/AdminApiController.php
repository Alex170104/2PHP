<?php

namespace App\Controller;

use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminApiController extends AbstractController
{
    #[Route('/admin', name: 'admin_loader')]
    public function adminLoader(Request $request, JWTTokenManagerInterface $jwtManager, UserRepository $userRepository, TournamentRepository $tournamentRepository): Response
    {
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            return $this->redirect('/login'); // ou une 403
        }

        try {
            $decoded = $jwtManager->parse($token);
            $user = $userRepository->findOneBy(['email' => $decoded['username']]);

            if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->render('error.html.twig', ['message' => 'Accès refusé']);
            }

            $users = $userRepository->findAll();
            $tournaments = $tournamentRepository->findAll();

            return $this->render('admin_api/indexAdmin.html.twig', [
                'user' => $user,
                'users' => $users,
                'tournaments' => $tournaments,
            ]);
        } catch (\Exception $e) {
            return new Response('Token invalide', 403);
        }
    }
}


