<?php

namespace App\Controller;

use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\TournamentRepository;
use App\Repository\PlayerRepository;
use App\Entity\Player;
use App\Entity\Registration;
use App\Repository\UserRepository;

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

    #[Route('/api/tournaments/{id}/registrations', name: 'api_register_player', methods: ['POST'])]
    public function registerPlayer(
        int $id,
        Request $request,
        TournamentRepository $tournamentRepository,
        UserRepository $userRepository,
        PlayerRepository $playerRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);

        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (
            !isset($data['user_id']) ||
            !isset($data['pseudo']) ||
            !isset($data['age'])
        ) {
            return $this->json(['error' => 'Pseudo, âge et user_id sont requis.'], 400);
        }

        $user = $userRepository->find($data['user_id']);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        $existingPlayer = $playerRepository->findOneBy(['user' => $user]);

        if ($existingPlayer) {
            $player = $existingPlayer;
        } else {
            $player = new Player();
            $player->setUser($user);
            $player->setPseudo($data['pseudo']);
            $player->setAge($data['age']);
            $player->setSport($tournament->getSport());
            $em->persist($player);
        }

        // Vérifier si le joueur est déjà inscrit à ce tournoi
        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'player' => $player,
            'tournament' => $tournament
        ]);

        if ($existingRegistration) {
            return $this->json([
                'error' => 'Ce joueur est déjà inscrit à ce tournoi.'
            ], 409);
        }


        $registration = new Registration();
        $registration->setPlayer($player);
        $registration->setTournament($tournament);
        $registration->setStatut('en attente');

        $em->persist($registration);
        $em->flush();

        return $this->json([
            'message' => 'Inscription enregistrée avec succès.',
            'registration_id' => $registration->getId()
        ], 201);
    }
    #[Route('/api/tournaments/{id}/registrations', name: 'api_get_registrations', methods: ['GET'])]
    public function getRegistrations(
        int $id,
        TournamentRepository $tournamentRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        // Récupérer le tournoi à partir de l'ID
        $tournament = $tournamentRepository->find($id);

        // Vérifier si le tournoi existe
        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        // Récupérer toutes les inscriptions pour ce tournoi
        $registrations = $em->getRepository(Registration::class)->findBy(['tournament' => $tournament]);

        // Si aucune inscription n'est trouvée
        if (empty($registrations)) {
            return $this->json(['message' => 'Aucune inscription trouvée pour ce tournoi.'], 404);
        }

        // Préparer les données des inscriptions
        $registrationData = [];
        foreach ($registrations as $registration) {
            $registrationData[] = [
                'id' => $registration->getId(),
                'player_id' => [
                    'player_id'=>$registration->getPlayer()->getId(),
                    'user_id' => $registration->getPlayer()->getUser()->getId(),
                    'pseudo' => $registration->getPlayer()->getPseudo(),
                    'age' => $registration->getPlayer()->getAge(),
                    'status' => $registration->getStatut(),
                    'sport' => $registration->getPlayer()->getSport(),
                    ],
                'tournament' => [
                    'id' => $registration->getTournament()->getId(),
                    'name' => $registration->getTournament()->getNom(),
                    'startDate' => $registration->getTournament()->getDateDebut()->format('Y-m-d'),
                    'endDate' => $registration->getTournament()->getDateFin()->format('Y-m-d'),
                    'location' => $registration->getTournament()->getLieu(),
                    'sport' => $registration->getTournament()->getSport(),
                ]
            ];
        }

        // Retourner la réponse JSON avec les données des inscriptions
        return $this->json([
            'message' => 'Inscriptions récupérées avec succès.',
            'registrations' => $registrationData
        ], 200);
    }

    #[Route('/api/tournaments/{idTournament}/registrations/{idRegistration}', name: 'api_delete_registration', methods: ['DELETE'])]    public function deleteRegistration(
        int $idTournament,
        int $idRegistration,
        TournamentRepository $tournamentRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        // Récupérer le tournoi avec l'ID
        $tournament = $tournamentRepository->find($idTournament);

        // Vérifier si le tournoi existe
        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        // Récupérer l'inscription avec l'ID
        $registration = $em->getRepository(Registration::class)->find($idRegistration);

        // Vérifier si l'inscription existe et appartient bien à ce tournoi
        if (!$registration) {
            return $this->json(['error' => 'Inscription introuvable.'], 404);
        }

        if ($registration->getTournament() !== $tournament) {
            return $this->json(['error' => 'L\'inscription ne correspond pas à ce tournoi.'], 400);
        }

        try {
            // Supprimer l'inscription
            $em->remove($registration);
            $em->flush();

            // Réponse JSON en cas de succès
            return $this->json(['message' => 'Inscription annulée avec succès.'], 200);
        } catch (\Exception $e) {
            // Gestion des erreurs
            return $this->json(['error' => 'Une erreur est survenue lors de la suppression de l\'inscription.'], 500);
        }
    }




}