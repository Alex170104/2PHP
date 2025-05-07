<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;  // Utilise l'exception de Symfony
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport as AuthPassport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class JWTAuthenticator extends AbstractAuthenticator
{
    private $jwtManager;
    private $userProvider;

    public function __construct(JWTTokenManagerInterface $jwtManager, UserProviderInterface $userProvider)
    {
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
    }

    public function supports(Request $request): ?bool
    {
        return $request->cookies->has('BEARER') ? true : null;
    }

    public function authenticate(Request $request): AuthPassport
    {
        $token = $request->cookies->get('BEARER');

        if (!$token) {
            throw new AuthenticationException('Token not found');
        }

        // Décoder le jeton brut
        $data = $this->jwtManager->decode($token); // Utilisez `parse` ou une méthode équivalente pour décoder une chaîne brute
        if (!$data) {
            throw new AuthenticationException('Invalid token');
        }

        if (!$data || !isset($data['email'])) {
            throw new AuthenticationException('Invalid token or email not found');
        }

        return new AuthPassport(
            new UserBadge($data['email']),
            new PasswordCredentials('')
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(['error' => 'Authentication failed: ' . $exception->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?JsonResponse
    {
        if (!$token instanceof TokenInterface) {
            return new JsonResponse(['error' => 'Invalid token'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $token->getUser();

        return new JsonResponse([
            'message' => 'Authentication successful',
            'user' => $user->getUsername()
        ], JsonResponse::HTTP_OK);
    }
}
