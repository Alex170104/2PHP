<?php

namespace App\DataFixtures;

use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@tournament.fr');
        $admin->setNom('Admin');
        $admin->setRoles(['ROLE_ADMIN']);

        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'admin1234'
        );
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        $users = [
            ['email' => 'tanguy@tournament.fr', 'nom' => 'Tanguy'],
            ['email' => 'alex@tournament.fr', 'nom' => 'Alex'],
            ['email' => 'tom@tournament.fr', 'nom' => 'Tom'],
        ];

        foreach ($users as $userData) {
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setNom($userData['nom']);
            $user->setRoles(['ROLE_USER']);

            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                '1234'
            );
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        $tournament1 = new Tournament();
        $tournament1->setNom('Tournoi de Football');
        $tournament1->setLieu('Paris');
        $tournament1->setDateDebut(new \DateTimeImmutable('2025-12-01'));
        $tournament1->setDateFin(new \DateTimeImmutable('2025-12-10'));
        $tournament1->setRegles('Un tournoi de football amical.');
        $tournament1->setOrganisateur($admin);
        $tournament1->setSport('football');

        $tournament2 = new Tournament();
        $tournament2->setNom('Tournoi de Basketball');
        $tournament2->setLieu('Lyon');
        $tournament2->setDateDebut(new \DateTimeImmutable('2023-12-15'));
        $tournament2->setDateFin(new \DateTimeImmutable('2023-12-20'));
        $tournament2->setRegles('Un tournoi de basketball compétitif.');
        $tournament2->setOrganisateur($admin);
        $tournament2->setSport('basketball');

        $manager->persist($tournament1);
        $manager->persist($tournament2);

        $manager->flush();
    }
}
