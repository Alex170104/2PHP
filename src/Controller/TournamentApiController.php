<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Registration;
use App\Entity\Tournament;
use App\Repository\PlayerRepository;
use App\Repository\RegistrationRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Entity\Rencontre;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\RencontreRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\MailService;

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
    public function showTournament(
        Tournament $tournament,
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        UserRepository $userRepository,
        PlayerRepository $playerRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $players = $playerRepository->findAll();
        $isAdminOrOwner = false;
        $isPlayerInTournament = false;
        $registration = null;

        $registrations = $entityManager->getRepository(Registration::class)
            ->createQueryBuilder('r')
            ->where('r.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->getQuery()
            ->getResult();

        $token = $request->cookies->get('BEARER');
        if ($token) {
            $payload = $jwtManager->parse($token);
            if ($payload && isset($payload['username'])) {
                $user = $userRepository->findOneBy(['email' => $payload['username']]);
                if ($user) {
                    $isAdminOrOwner = in_array('ROLE_ADMIN', $user->getRoles()) ||
                        $user === $tournament->getOrganisateur();

                    foreach ($players as $player) {
                        if ($player->getUser() === $user) {
                            $isPlayerInTournament = true;

                            $registration = $entityManager->getRepository(Registration::class)->findOneBy([
                                'player' => $player,
                                'tournament' => $tournament
                            ]);
                            break;
                        }
                    }
                }
            }
        }

        return $this->render('tournament/indexTournament.html.twig', [
            'tournament' => $tournament,
            'players' => $players,
            'isAdminOrOwner' => $isAdminOrOwner,
            'isPlayerInTournament' => $isPlayerInTournament,
            'registration' => $registration,
            'registrations' => $registrations,
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
            // Supprimer le tournoi (les entités liées seront supprimées automatiquement)
            $entityManager->remove($tournament);
            $entityManager->flush();

            return new JsonResponse(
                ['message' => 'Tournoi et ses données associées supprimés avec succès.'],
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
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

    #[Route('/api/tournaments/{id}/registrations', name: 'api_register_player', methods: ['POST'])]
    public function registerPlayer(
        int $id,
        Request $request,
        TournamentRepository $tournamentRepository,
        UserRepository $userRepository,
        PlayerRepository $playerRepository,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $tournament = $tournamentRepository->find($id);
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            return $this->json(['error' => 'Token manquant.'], 401);
        }

        $decoded = $jwtManager->parse($token);
        $userFromToken = $userRepository->findOneBy(['email' => $decoded['username']]);

        if (!$userFromToken) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['pseudo']) || !isset($data['age'])) {
            return $this->json(['error' => 'Pseudo et âge sont requis.'], 400);
        }

        $user = $userFromToken;

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

        $existingRegistration = $em->getRepository(Registration::class)->findOneBy([
            'player' => $player,
            'tournament' => $tournament
        ]);

        if ($existingRegistration) {
            return $this->json(['error' => 'Ce joueur est déjà inscrit à ce tournoi.'], 409);
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

        $player = $registration->getPlayer();

        try {

            if($player){
                $em->remove($player);
            }

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

    #[Route('/api/registrations/{id}/accept', name: 'api_accept_registration', methods: ['POST'])]
    public function acceptRegistration(int $id, EntityManagerInterface $em): JsonResponse {
        $registration = $em->getRepository(Registration::class)->findOneBy(['player' => $id]);

        if (!$registration) {
            return $this->json(['error' => 'Inscription introuvable.'], 404);
        }

        $registration->setStatut('accepté');
        $em->flush();

        return $this->json(['message' => 'Inscription acceptée avec succès.']);
    }

    #[Route('/api/registrations/{id}/reject', name: 'api_reject_registration', methods: ['POST'])]
    public function rejectRegistration(int $id, EntityManagerInterface $em): JsonResponse {
        $registration = $em->getRepository(Registration::class)->find($id);

        if (!$registration) {
            return $this->json(['error' => 'Inscription introuvable.'], 404);
        }

        $em->remove($registration);
        $em->flush();

        return $this->json(['message' => 'Inscription rejetée avec succès.']);
    }


    #[Route('/tournament/{id}/bracket', name: 'tournament_bracket', methods: ['GET'])]
    public function showBracket(Tournament $tournament): Response
    {
        return $this->render('tournament/bracket.html.twig', [
            'tournament' => $tournament,
        ]);
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
    public function createMatchesForTournament(
        int $id,
        EntityManagerInterface $entityManager,
        TournamentRepository $tournamentRepository,
        RencontreRepository $rencontreRepository,
        RegistrationRepository $registrationRepository
    ): JsonResponse {
        // Récupérer le tournoi
        $tournament = $tournamentRepository->find($id);
        if (!$tournament) {
            return new JsonResponse(['error' => 'Tournoi introuvable.'], 404);
        }

        // Vérifier si des rencontres existent déjà
        $existingMatches = $rencontreRepository->findBy(['tournament' => $tournament]);
        if (!empty($existingMatches)) {
            return new JsonResponse(['message' => 'Les rencontres existent déjà.'], 200);
        }

        // Récupérer les joueurs avec le statut "accepté"
        $registrations = $registrationRepository->findBy([
            'tournament' => $tournament,
            'statut' => 'accepté'
        ]);

        $players = array_map(fn($registration) => $registration->getPlayer(), $registrations);

        // Vérifier qu'il y a un nombre pair de joueurs
        if (count($players) < 2) {
            return new JsonResponse(['error' => 'Pas assez de joueurs pour générer des rencontres.'], 400);
        }

        // Mélanger les joueurs pour des paires aléatoires
        shuffle($players);

        // Générer des paires de joueurs
        $pairs = array_chunk($players, 2);

        // Créer des objets Rencontre pour chaque paire
        foreach ($pairs as $pair) {
            if (count($pair) === 2) {
                $match = new Rencontre();
                $match->setEquipe1($pair[0]);
                $match->setEquipe2($pair[1]);
                $match->setTournament($tournament);
                $match->setScore1(0);
                $match->setScore2(0);

                $entityManager->persist($match);
            }
        }

        // Enregistrer les rencontres dans la base de données
        $entityManager->flush();

        return new JsonResponse(['message' => 'Rencontres générées avec succès.'], 201);
    }

    #[Route('/api/tournaments/{idTournament}/sport-matchs/{idRencontre}', name: 'api_tournament_sport_matchs_details', methods: ['GET'])]
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
        MailService $mailService,
    ): JsonResponse {
        $tournament = $tournamentRepository->find($idTournament);
        if (!$tournament) {
            return $this->json(['error' => 'Tournoi introuvable.'], 404);
        }

        $match = $rencontreRepository->find($idSportMatch);
        if (!$match || $match->getTournament()->getId() !== $tournament->getId()) {
            return $this->json(['error' => 'Match introuvable pour ce tournoi.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['score1']) || !isset($data['score2'])) {
            return $this->json(['error' => 'Les scores sont requis.'], 400);
        }

        $match->setScore1($data['score1']);
        $match->setScore2($data['score2']);

        // Déterminer le gagnant
        if ($data['score1'] > $data['score2']) {
            $match->setWinner($match->getEquipe1());
        } elseif ($data['score2'] > $data['score1']) {
            $match->setWinner($match->getEquipe2());
        } else {
            return $this->json(['error' => 'Les scores ne peuvent pas être égaux.'], 400);
        }

        $em->flush();

        $joueur1 = $match->getEquipe1();
        $joueur2 = $match->getEquipe2();

        $user1 = $joueur1->getUser();
        $user2 = $joueur2->getUser();

        // Envoyer un email aux joueurs
        $email1 = $user1->getEmail();
        $email2 = $user2->getEmail();



        // Vérifier si tous les matchs du tour actuel sont terminés
        $currentMatches = $rencontreRepository->findBy(['tournament' => $tournament]);
        $allFinished = true;
        foreach ($currentMatches as $currentMatch) {
            if (!$currentMatch->getWinner()) {
                $allFinished = false;
                break;
            }
        }

        // Générer les matchs du tour suivant si tous les matchs sont terminés
        if ($allFinished) {
            $winners = array_map(fn($m) => $m->getWinner(), $currentMatches);
            shuffle($winners);
            $pairs = array_chunk($winners, 2);

            foreach ($pairs as $pair) {
                if (count($pair) === 2) {
                    $nextMatch = new Rencontre();
                    $nextMatch->setEquipe1($pair[0]);
                    $nextMatch->setEquipe2($pair[1]);
                    $nextMatch->setTournament($tournament);
                    $nextMatch->setScore1(0);
                    $nextMatch->setScore2(0);

                    $em->persist($nextMatch);
                }
            }
            $em->flush();
        }

        $mailService->send(
            [$email1, $email2],
            'Résultat du match',
            'Votre match a été terminé. Voici les résultats : ' . "\n" .
            'Joueur 1 : ' . $joueur1->getPseudo() . ' - Score : ' . $data['score1'] . "\n" .
            'Joueur 2 : ' . $joueur2->getPseudo() . ' - Score : ' . $data['score2'] . "\n" .
            'Gagnant : ' . ($match->getWinner() ? $match->getWinner()->getPseudo() : 'Aucun gagnant') . "\n"
        );

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