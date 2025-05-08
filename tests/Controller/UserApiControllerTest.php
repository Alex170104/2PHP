<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserApiControllerTest extends WebTestCase
{
    public function testUserLoaderRedirectsIfNoToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/user');

        $this->assertResponseRedirects('/login');
    }

    public function testSecureUserWithInvalidToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/Api/user', [], [], [
            'HTTP_Authorization' => 'Bearer invalidtoken'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertStringContainsString('Token invalide', $client->getResponse()->getContent());
    }

    public function testLogoutClearsCookieAndRedirects(): void
    {
        $client = static::createClient();
        $client->request('POST', '/logout');

        $this->assertResponseRedirects('/');
        $this->assertTrue($client->getResponse()->headers->has('Set-Cookie'));
    }

    public function testDeleteAccountAsAdminRedirects(): void
    {
        $client = static::createClient();

        // Simule un utilisateur admin
        $user = new User();
        $user->setEmail('admin@test.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('password');

        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('POST', '/delete-account/' . $user->getId());

        $this->assertResponseRedirects('/user/' . $user->getId());
    }

    public function testUpdateAccountFailsIfCurrentPasswordWrong(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $user = new User();
        $user->setNom('Test');
        $user->setEmail('test@update.com');
        $user->setPassword(password_hash('correct', PASSWORD_BCRYPT));
        $entityManager->persist($user);
        $entityManager->flush();

        $client->request('POST', '/update-account/' . $user->getId(), [
            'nom' => 'New Name',
            'email' => 'new@mail.com',
            'new_password' => 'newpass',
            'current_password' => 'wrong'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertStringContainsString('Mot de passe actuel incorrect', $client->getResponse()->getContent());
    }
}
