<?php

namespace App\Entity;

use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CardRepository::class)]
class Card
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\Column]
    private int $seed = 1;

    #[ORM\Column(type: 'json')]
    private array $grid = [];

    #[ORM\Column(type: 'json')]
    private array $flatNumbers = [];

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Claim>
     */
    #[ORM\OneToMany(mappedBy: 'card', targetEntity: Claim::class)]
    private Collection $claims;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->claims = new ArrayCollection();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
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

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function setSeed(int $seed): static
    {
        $this->seed = $seed;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Claim>
     */
    public function getClaims(): Collection
    {
        return $this->claims;
    }

    public function addClaim(Claim $claim): static
    {
        if (!$this->claims->contains($claim)) {
            $this->claims->add($claim);
            $claim->setCard($this);
        }

        return $this;
    }

    public function removeClaim(Claim $claim): static
    {
        if ($this->claims->removeElement($claim)) {
            if ($claim->getCard() === $this) {
                $claim->setCard(null);
            }
        }

        return $this;
    }
}
