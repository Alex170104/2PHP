<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TournamentApiControllerTest extends WebTestCase
{
    public function testGetRegistrationsWithoutTournament(): void
    {
        $client = static::createClient();

        // ID inexistant
        $client->request('GET', '/api/tournaments/999999/registrations');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Tournoi introuvable.', $data['error']);
    }

    public function testCreateTournamentWithMissingData(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/Api/tournaments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test tournoi',
                // startDate manquant exprès
                'endDate' => '2025-12-31',
                'description' => 'desc',
                'sport' => 'Football',
                'lieu' => 'Paris'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJson($client->getResponse()->getContent());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
