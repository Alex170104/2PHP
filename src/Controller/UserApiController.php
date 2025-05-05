<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;



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

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        // Suppression du cookie 'BEARER' contenant le token
        $response = new Response();

        // Supprime le cookie BEARER
        $response->headers->setCookie(
            new Cookie(
                'BEARER',          // Nom du cookie
                '',                // Valeur vide
                time() - 3600,     // Expiration passée pour supprimer le cookie
                '/',               // Chemin
                null,              // Domaine
                false,             // Sécurisé (false en local, true en prod)
                true,              // HttpOnly (pour sécuriser le cookie)
                false,             // Raw (false si tu veux que le cookie soit encodé)
                Cookie::SAMESITE_LAX // Valeur de SameSite pour la sécurité
            )
        );

        // Redirige l'utilisateur vers la page d'accueil (http://localhost:8000/)
        $response->headers->set('Location', '/');  // Forcer la redirection vers la page d'accueil
        $response->setStatusCode(Response::HTTP_FOUND); // Utilise le code 302 pour la redirection

        return $response;
    }

    #[Route('/delete-account/{id}', name: 'app_delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $entityManager, int $id): Response
    {
        // Récupérer l'utilisateur via son ID
        $user = $entityManager->getRepository(User::class)->find($id);
        $role = $user->getRoles();
        $response = $this->redirectToRoute('home');
        if (!$user) {
            // Si l'utilisateur n'existe pas, renvoyer une erreur
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }
        if (in_array('ROLE_ADMIN', $role)) {
            return $this->redirectToRoute('user_loader_id', ['id' => $id]);
        }

        // Supprimer l'utilisateur de la base de données
        $entityManager->remove($user);
        $entityManager->flush();

         // Redirige vers la page d'accueil après suppression

        return $response;

        // Retourner la réponse pour la déconnexion et rediriger

    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('index.html.twig');
    }


}


