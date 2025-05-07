<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Tournament;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class TournamentApiController extends AbstractController
{
    private $jwtManager;
    private $userRepository;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ) {
        $this->jwtManager = $jwtManager;
        $this->userRepository = $userRepository;
    }

    #[Route('/tournament/{id}', name: 'tournament_show', methods: ['GET'])]
    public function showTournament(Tournament $tournament, EntityManagerInterface $entityManager, Request $request): Response
    {
        $players = null;
        return $this->render('tournament/indexTournament.html.twig', [
            'tournament' => $tournament,
            'players' => $players,
        ]);
    }

    #[Route('/Api/tournaments/{id}', name: 'api_update_tournament', methods: ['PUT'])]
    public function updateTournament(Tournament $tournament, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Décoder le JSON reçu dans le corps de la requête
        $data = json_decode($request->getContent(), true);

        // Vérification des champs obligatoires
        $name = $data['name'] ?? null;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $description = $data['description'] ?? null;
        $sport = $data['sport'] ?? null;
        $location = $data['lieu'] ?? null;

        if (!$name || !$startDate || !$endDate || !$description || !$sport || !$location) {
            return new JsonResponse(
                ['error' => 'Tous les champs sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Mise à jour des données du tournoi
            $tournament->setNom($name);
            $tournament->setDateDebut(new \DateTimeImmutable($startDate));
            $tournament->setDateFin(new \DateTimeImmutable($endDate));
            $tournament->setRegles($description);
            $tournament->setSport($sport);
            $tournament->setLieu($location);

            // Sauvegarde dans la base de données
            $entityManager->flush();

            // Réponse JSON en cas de succès
            return new JsonResponse(
                ['message' => 'Tournoi mis à jour avec succès.'],
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            // Gestion des erreurs
            return new JsonResponse(
                ['error' => 'Une erreur est survenue lors de la mise à jour du tournoi.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/Api/tournaments/{id}', name: 'api_delete_tournament', methods: ['DELETE'])]
    public function deleteTournament(Tournament $tournament, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            // Suppression du tournoi
            $entityManager->remove($tournament);
            $entityManager->flush();

            // Réponse JSON en cas de succès
            return new JsonResponse(
                ['message' => 'Tournoi supprimé avec succès.'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            // Gestion des erreurs
            return new JsonResponse(
                ['error' => 'Une erreur est survenue lors de la suppression du tournoi.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/Api/tournaments', name: 'api_create_tournament', methods: ['POST'])]
    public function createTournament(
        Request $request,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        $description = $data['description'] ?? null;
        $sport = $data['sport'] ?? null;
        $location = $data['lieu'] ?? null;

        if (!$name || !$startDate || !$endDate || !$description || !$sport || !$location) {
            return new JsonResponse(
                ['error' => 'Tous les champs sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // 🔐 Récupérer le token depuis le cookie et décoder
            $token = $request->cookies->get('BEARER');
            if (!$token) {
                return new JsonResponse(['error' => 'Token JWT manquant.'], Response::HTTP_UNAUTHORIZED);
            }

            $payload = $jwtManager->parse($token); // ça décode le token JWT

            if (!$payload || !isset($payload['username'])) {
                return new JsonResponse(['error' => 'Token JWT invalide.'], Response::HTTP_UNAUTHORIZED);
            }

            // 📥 Récupérer l'utilisateur via l'email contenu dans le token
            $user = $userRepo->findOneBy(['email' => $payload['username']]);
            if (!$user) {
                return new JsonResponse(['error' => 'Utilisateur introuvable.'], Response::HTTP_UNAUTHORIZED);
            }

            // 🏗️ Création du tournoi
            $tournament = new Tournament();
            $tournament->setNom($name);
            $tournament->setDateDebut(new \DateTimeImmutable($startDate));
            $tournament->setDateFin(new \DateTimeImmutable($endDate));
            $tournament->setRegles($description);
            $tournament->setSport($sport);
            $tournament->setLieu($location);
            $tournament->setOrganisateur($user);

            $entityManager->persist($tournament);
            $entityManager->flush();

            return new JsonResponse(['message' => 'Tournoi créé avec succès'], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse(
                ['error' => 'Une erreur est survenue lors de la création du tournoi.', 'details' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }



}