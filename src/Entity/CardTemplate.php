<?php

namespace App\Entity;

use App\Repository\CardTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardTemplateRepository::class)]
#[ORM\Index(columns: ['is_available'], name: 'idx_card_template_available')]
class CardTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'json')]
    private array $grid = [];

    #[ORM\Column(type: 'json')]
    private array $flatNumbers = [];

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGrid(): array
    {
        return $this->grid;
    }

    public function setGrid(array $grid): static
    {
        $this->grid = $grid;
        return $this;
    }

    public function getFlatNumbers(): array
    {
        return $this->flatNumbers;
    }

    public function setFlatNumbers(array $flatNumbers): static
    {
        $this->flatNumbers = $flatNumbers;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function markAsUsed(): static
    {
        $this->isAvailable = false;
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function release(): static
    {
        $this->isAvailable = true;
        return $this;
    }
}
