<?php

namespace App\Controller\Api;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;

class LoginController extends AbstractController
{
    private $jwtManager;
    private $passwordHasher;
    private $userProvider;

    public function __construct(
        JWTTokenManagerInterface $jwtManager,
        UserPasswordHasherInterface $passwordHasher,
        UserProviderInterface $userProvider,
        private TokenStorageInterface $tokenStorage
    ) {
        $this->jwtManager = $jwtManager;
        $this->passwordHasher = $passwordHasher;
        $this->userProvider = $userProvider;
    }

    public function login(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'];
        $password = $data['password'];

        // Récupérer l'utilisateur par son email
        $user = $this->userProvider->loadUserByIdentifier($email);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Identifiants invalides'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Générer un token JWT
        $token = $this->jwtManager->create($user);

        $preAuthenticatedToken = new PreAuthenticatedToken($user, $token, $user->getRoles());
        $this->tokenStorage->setToken($preAuthenticatedToken);

        $response = new JsonResponse([
            'status' => 'ok',
            'role' => $user->getRoles()[0]
        ]);

        $response->headers->setCookie(
            Cookie::create('BEARER', $token, time() + 3600, '/', null, false, false)
        );

        return $response;
    }
}