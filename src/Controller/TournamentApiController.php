<?php

namespace App\Controller;

use App\Entity\Tournament;
use App\Entity\Rencontre;
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
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\RencontreRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;

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

    #[Route('/api/tournaments/{idTournament}/registrations/{idRegistration}', name: 'api_delete_registration', methods: ['DELETE'])]
    public function deleteRegistration(
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


    #[Route('/api/tournaments/{id}/sport-matchs', name: 'api_tournament_sport_matchs', methods: ['GET'])]
    public function getRencontresByTournament(
        int $id,
        TournamentRepository $tournamentRepository,
        RencontreRepository $rencontreRepository
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);

        if (!$tournament) {
            return new JsonResponse(['error' => 'Tournoi non trouvé.'], 404);
        }

        $rencontres = $rencontreRepository->findBy(['tournament' => $tournament]);

        if (empty($rencontres)) {
            return $this->json(['message' => 'Aucune rencontre trouvée pour ce tournoi.'], 404);
        }

        $rencontreData = [];
        foreach ($rencontres as $rencontre) {
            $rencontreData[] = [
                'id' => $rencontre->getId(),
                'joueur1' => $rencontre->getEquipe1() ? [
                    'id' => $rencontre->getEquipe1()->getId(),
                    'pseudo' => $rencontre->getEquipe1()->getPseudo(),
                ] : null,
                'joueur2' => $rencontre->getEquipe2() ? [
                    'id' => $rencontre->getEquipe2()->getId(),
                    'pseudo' => $rencontre->getEquipe2()->getPseudo(),
                ] : null,
                'scoreJoueur1' => $rencontre->getScore1(),
                'scoreJoueur2' => $rencontre->getScore2(),
                'winner' => $rencontre->getWinner() ? [
                    'id' => $rencontre->getWinner()->getId(),
                    'pseudo' => $rencontre->getWinner()->getPseudo(),
                ] : null,
                'tournament' => [
                    'id' => $tournament->getId(),
                    'name' => $tournament->getNom(),
                    'sport' => $tournament->getSport(),
                ]
            ];
        }

        return $this->json([
            'message' => 'Rencontres récupérées avec succès.',
            'rencontres' => $rencontreData
        ], 200);
    }


    #[Route('/api/tournaments/{id}/sport-matchs', name: 'api_create_sport_match', methods: ['POST'])]
    public function createSportMatchForTournament(
        int $id,
        Request $request,
        TournamentRepository $tournamentRepository,
        PlayerRepository $player1Repository,
        PlayerRepository $player2Repository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);

        if (!$tournament) {
            return new JsonResponse(['error' => 'Tournoi non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['player1_id'], $data['player2_id'])) {
            return new JsonResponse(['error' => 'Données manquantes (player1_id, player2_id)'], 400);
        }

        $player1 = $player1Repository->find($data['player1_id']);
        $player2 = $player2Repository->find($data['player2_id']);

        if (!$player1 || !$player2) {
            return new JsonResponse(['error' => 'Un ou plusieurs joueurs sont introuvables'], 404);
        }

        $match = new Rencontre();
        $match->setEquipe1($player1);
        $match->setEquipe2($player2);
        $match->setTournament($tournament);
        $match->setScore1(0); // Initialiser le score à 0
        $match->setScore2(0); // Initialiser le score à 0
        $entityManager->persist($match);
        $entityManager->flush();

        $json = $serializer->serialize($match, 'json', ['groups' => 'sport_match_read']);

        return new JsonResponse($json, 201, [], true);
    }

    #[Route('/api/tournaments/{idTournament}/sport-matchs/{idRencontre}', name: 'api_tournament_sport_matchs', methods: ['GET'])]
    public function getRencontreByTournament(
        int $idTournament,
        int $idRencontre,
        TournamentRepository $tournamentRepository,
        RencontreRepository $rencontreRepository
    ): JsonResponse {
        $tournament = $tournamentRepository->find($idTournament);

        if (!$tournament) {
            return new JsonResponse(['error' => 'Tournoi non trouvé.'], 404);
        }

        $rencontre = $rencontreRepository->find($idRencontre);

        if (!$rencontre) {
            return new JsonResponse(['error' => 'Rencontre non trouvé.'], 404);;
        }
        $rencontreData[] = [
            'id' => $rencontre->getId(),
            'joueur1' => $rencontre->getEquipe1() ? [
                'id' => $rencontre->getEquipe1()->getId(),
                'pseudo' => $rencontre->getEquipe1()->getPseudo(),
            ] : null,
            'joueur2' => $rencontre->getEquipe2() ? [
                'id' => $rencontre->getEquipe2()->getId(),
                'pseudo' => $rencontre->getEquipe2()->getPseudo(),
            ] : null,
            'scoreJoueur1' => $rencontre->getScore1(),
            'scoreJoueur2' => $rencontre->getScore2(),
            'winner' => $rencontre->getWinner() ? [
                'id' => $rencontre->getWinner()->getId(),
                'pseudo' => $rencontre->getWinner()->getPseudo(),
            ] : null,
            'tournament' => [
                'id' => $tournament->getId(),
                'name' => $tournament->getNom(),
                'sport' => $tournament->getSport(),
            ]
        ];


        return $this->json([
            'message' => 'Rencontres récupérées avec succès.',
            'rencontres' => $rencontreData
        ], 200);
    }

    #[Route('/api/tournaments/{idTournament}/sport-matchs/{idSportMatch}', name: 'api_update_sport_match', methods: ['PUT'])]
    public function updateSportMatch(
        int $idTournament,
        int $idSportMatch,
        Request $request,
        TournamentRepository $tournamentRepository,
        RencontreRepository $rencontreRepository,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository
    ): JsonResponse {
        $tournament = $tournamentRepository->find($idTournament);
        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        $match = $rencontreRepository->find($idSportMatch);
        if (!$match || $match->getTournament()->getId() !== $tournament->getId()) {
            return $this->json(['error' => 'Match introuvable pour ce tournoi.'], 404);
        }

        $token = $request->cookies->get('BEARER');
        if (!$token) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], 401);
        }

        $payload = $jwtManager->parse($token);
        if (!isset($payload['username'])) {
            return $this->json(['error' => 'Token invalide.'], 401);
        }

        $user = $userRepository->findOneBy(['email' => $payload['username']]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], 401);
        }

        $isAdminOrOwner = in_array('ROLE_ADMIN', $user->getRoles()) || $user === $tournament->getOrganisateur();

        $isPlayerInMatch = false;
        if ($match->getEquipe1() && $match->getEquipe1()->getUser() === $user) {
            $isPlayerInMatch = true;
        } elseif ($match->getEquipe2() && $match->getEquipe2()->getUser() === $user) {
            $isPlayerInMatch = true;
        }

        if (!$isAdminOrOwner && !$isPlayerInMatch) {
            return $this->json(['error' => 'Accès refusé. Seul un joueur du match ou un admin peut modifier le score.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Le corps de la requête est vide ou invalide.'], 400);
        }

        if ($isAdminOrOwner) {
            if (isset($data['score1'])) {
                $match->setScore1($data['score1']);
            }
            if (isset($data['score2'])) {
                $match->setScore2($data['score2']);
            }
        } else {
            if (!isset($data['score'])) {
                return $this->json(['error' => 'Le score est requis.'], 400);
            }

            if ($match->getEquipe1() && $match->getEquipe1()->getUser() === $user) {
                $match->setScore1($data['score']);
            } elseif ($match->getEquipe2() && $match->getEquipe2()->getUser() === $user) {
                $match->setScore2($data['score']);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Score mis à jour avec succès.']);
    }


    #[Route('/api/tournaments/{idTournament}/sport-matchs/{idSportMatchs}', name: 'api_delete_sport_match', methods: ['DELETE'])]
    public function deleteSportMatch(
        int $idTournament,
        int $idSportMatchs,
        Request $request,
        TournamentRepository $tournamentRepository,
        RencontreRepository $rencontreRepository,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository
    ): JsonResponse {
        // Récupérer le tournoi
        $tournament = $tournamentRepository->find($idTournament);
        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        // Récupérer la rencontre (match)
        $match = $rencontreRepository->find($idSportMatchs);
        if (!$match || $match->getTournament()->getId() !== $tournament->getId()) {
            return $this->json(['error' => 'Match introuvable pour ce tournoi.'], 404);
        }

        // Authentification via cookie
        $token = $request->cookies->get('BEARER');
        if (!$token) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], 401);
        }

        $payload = $jwtManager->parse($token);
        if (!$payload || !isset($payload['username'])) {
            return $this->json(['error' => 'Token invalide.'], 401);
        }

        $user = $userRepository->findOneBy(['email' => $payload['username']]);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], 404);
        }

        // Vérification des droits : admin ou organisateur uniquement
        $isAdminOrOwner = in_array('ROLE_ADMIN', $user->getRoles()) || $user === $tournament->getOrganisateur();
        if (!$isAdminOrOwner) {
            return $this->json(['error' => 'Accès refusé. Seul un administrateur ou l\'organisateur peut supprimer ce match.'], 403);
        }

        // Suppression de la rencontre
        $em->remove($match);
        $em->flush();

        return $this->json(['message' => 'Match supprimé avec succès.'], 200);
    }





}