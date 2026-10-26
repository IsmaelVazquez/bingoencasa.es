<?php

namespace App\Entity;

use App\Enum\ClaimStatus;
use App\Enum\ClaimType;
use App\Repository\ClaimRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ClaimRepository::class)]
class Claim
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(inversedBy: 'claims')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'claims')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Card $card = null;

    #[ORM\ManyToOne(inversedBy: 'claims')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\Column(length: 50)]
    private string $type = 'BINGO';

    #[ORM\Column(length: 50)]
    private string $status = 'PENDIENTE';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getCard(): ?Card
    {
        return $this->card;
    }

    public function setCard(?Card $card): static
    {
        $this->card = $card;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeEnum(): ClaimType
    {
        return ClaimType::tryFrom($this->type) ?? ClaimType::BINGO;
    }

    public function setType(string|ClaimType $type): static
    {
        $this->type = $type instanceof ClaimType ? $type->value : $type;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusEnum(): ClaimStatus
    {
        return ClaimStatus::tryFrom($this->status) ?? ClaimStatus::PENDIENTE;
    }

    public function setStatus(string|ClaimStatus $status): static
    {
        $this->status = $status instanceof ClaimStatus ? $status->value : $status;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
