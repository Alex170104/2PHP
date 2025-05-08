<?php

namespace App\Tests\Controller;

use App\Controller\AdminApiController;
use App\Entity\User;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class AdminApiControllerTest extends TestCase
{
    private function createControllerWithDependencies(
        $token = null,
        $decodedToken = null,
        $user = null,
        $users = [],
        $tournaments = []
    ): Response {
        $request = new Request();
        if ($token) {
            $request->cookies->set('BEARER', $token);
        }

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        if ($token) {
            $jwtManager->expects($this->once())
                ->method('parse')
                ->with($token)
                ->willReturn($decodedToken);
        }

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);
        $userRepository->method('findAll')->willReturn($users);

        $tournamentRepository = $this->createMock(TournamentRepository::class);
        $tournamentRepository->method('findAll')->willReturn($tournaments);

        $controller = new AdminApiController();

        // Fake Twig render
        $controller->setContainer($this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class));

        // Render is protected by AbstractController, we simulate it with reflection
        $reflection = new \ReflectionClass(AdminApiController::class);
        $method = $reflection->getMethod('adminLoader');
        $method->setAccessible(true);

        return $method->invoke($controller, $request, $jwtManager, $userRepository, $tournamentRepository);
    }

    public function testRedirectsToLoginIfNoToken()
    {
        $response = $this->createControllerWithDependencies();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function testTokenInvalidReturns403()
    {
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('parse')->willThrowException(new \Exception());

        $request = new Request();
        $request->cookies->set('BEARER', 'invalid_token');

        $userRepository = $this->createMock(UserRepository::class);
        $tournamentRepository = $this->createMock(TournamentRepository::class);

        $controller = new AdminApiController();
        $controller->setContainer($this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class));

        $reflection = new \ReflectionClass(AdminApiController::class);
        $method = $reflection->getMethod('adminLoader');
        $method->setAccessible(true);

        $response = $method->invoke($controller, $request, $jwtManager, $userRepository, $tournamentRepository);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Token invalide', $response->getContent());
    }

    public function testAccessDeniedIfUserNotAdmin()
    {
        $decodedToken = ['username' => 'admin@test.com'];

        $user = new User();
        $user->setEmail('admin@test.com');
        $user->setRoles(['ROLE_USER']); // Pas admin

        $response = $this->createControllerWithDependencies(
            'valid_token',
            $decodedToken,
            $user
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Accès refusé', $response->getContent());
    }

    public function testAccessGrantedIfAdmin()
    {
        $decodedToken = ['username' => 'admin@test.com'];

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setRoles(['ROLE_ADMIN']);

        $response = $this->createControllerWithDependencies(
            'valid_token',
            $decodedToken,
            $adminUser,
            [/* liste users */],
            [/* liste tournois */]
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
