<?php

namespace App\Entity;

use App\Repository\RencontreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RencontreRepository::class)]
class Rencontre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rencontres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $equipe1 = null;

    #[ORM\ManyToOne(inversedBy: 'rencontres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $equipe2 = null;

    #[ORM\Column]
    private ?int $score1 = null;

    #[ORM\Column]
    private ?int $score2 = null;

    #[ORM\ManyToOne(inversedBy: 'rencontres')]
    private ?Player $winner = null;

    #[ORM\ManyToOne(inversedBy: 'rencontres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournament $tournament = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipe1(): ?Player
    {
        return $this->equipe1;
    }

    public function setEquipe1(?Player $equipe1): static
    {
        $this->equipe1 = $equipe1;

        return $this;
    }

    public function getEquipe2(): ?Player
    {
        return $this->equipe2;
    }

    public function setEquipe2(?Player $equipe2): static
    {
        $this->equipe2 = $equipe2;

        return $this;
    }

    public function getScore1(): ?int
    {
        return $this->score1;
    }

    public function setScore1(int $score1): static
    {
        $this->score1 = $score1;

        return $this;
    }

    public function getScore2(): ?int
    {
        return $this->score2;
    }

    public function setScore2(int $score2): static
    {
        $this->score2 = $score2;

        return $this;
    }

    public function getWinner(): ?Player
    {
        return $this->winner;
    }

    public function setWinner(?Player $winner): static
    {
        $this->winner = $winner;

        return $this;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): static
    {
        $this->tournament = $tournament;

        return $this;
    }
}
