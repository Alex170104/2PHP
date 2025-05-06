<?php

namespace App\Controller;

use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TournamentApiController extends AbstractController
{
    #[Route('/tournament/{id}', name: 'tournament_show', methods: ['GET'])]
    public function showTournament(Tournament $tournament, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->render('tournament/show.html.twig', [
            'tournament' => $tournament,
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

    #[Route('/api/tournaments/{id}', name: 'api_delete_tournament', methods: ['DELETE'])]
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
    public function createTournament(Request $request, EntityManagerInterface $entityManager): JsonResponse
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
            // Création de l'entité Tournament
            $tournament = new Tournament();
            $tournament->setNom($name);
            $tournament->setDateDebut(new \DateTimeImmutable($startDate));
            $tournament->setDateFin(new \DateTimeImmutable($endDate));
            $tournament->setRegles($description);
            $tournament->setSport($sport);
            $tournament->setLieu($location);

            // Associer l'utilisateur connecté comme organisateur
            $tournament->setOrganisateur($this->getAuthenticatedUser($request));

            // Sauvegarde dans la base de données
            $entityManager->persist($tournament);
            $entityManager->flush();

            // Réponse JSON en cas de succès
            return new JsonResponse(['message' => 'Tournoi créé avec succès'], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            // Gestion des erreurs
            return new JsonResponse(
                ['error' => 'Une erreur est survenue lors de la création du tournoi.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function getAuthenticatedUser(Request $request): ?User
    {
        $token = $request->headers->get('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return null;
        }

        $jwt = substr($token, 7); // Supprime "Bearer "
        try {
            $decoded = $this->jwtManager->parse($jwt);
            $user = $this->userRepository->findOneBy(['email' => $decoded['username']]);
            return $user ? $user->getId() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}