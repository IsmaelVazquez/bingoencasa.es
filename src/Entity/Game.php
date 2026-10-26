<?php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(inversedBy: 'games')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\Column(length: 40)]
    private string $status = 'DETENIDA';

    #[ORM\Column]
    private int $seed = 1;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 20)]
    private string $prizeTarget = 'LINEA';

    #[ORM\Column(type: 'float')]
    private float $cardPrice = 0.0;

    #[ORM\Column(type: 'integer')]
    private int $lineaPrizePercent = 30;

    #[ORM\Column(type: 'integer')]
    private int $bingoPrizePercent = 70;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /**
     * @var Collection<int, Card>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Card::class, orphanRemoval: true)]
    private Collection $cards;

    /**
     * @var Collection<int, Draw>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Draw::class, orphanRemoval: true)]
    private Collection $draws;

    /**
     * @var Collection<int, Claim>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Claim::class, orphanRemoval: true)]
    private Collection $claims;

    public function __construct()
    {
        $this->cards = new ArrayCollection();
        $this->draws = new ArrayCollection();
        $this->claims = new ArrayCollection();
        $this->seed = random_int(1, 1000000);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusEnum(): GameStatus
    {
        return GameStatus::tryFrom($this->status) ?? GameStatus::DETENIDA;
    }

    public function setStatus(string|GameStatus $status): static
    {
        $this->status = $status instanceof GameStatus ? $status->value : $status;

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

    public function getPrizeTarget(): string
    {
        return $this->prizeTarget;
    }

    public function setPrizeTarget(string $prizeTarget): static
    {
        $this->prizeTarget = $prizeTarget;

        return $this;
    }

    public function getCardPrice(): float
    {
        return $this->cardPrice;
    }

    public function setCardPrice(float $cardPrice): static
    {
        $this->cardPrice = $cardPrice;

        return $this;
    }

    public function getLineaPrizePercent(): int
    {
        return $this->lineaPrizePercent;
    }

    public function setLineaPrizePercent(int $lineaPrizePercent): static
    {
        $this->lineaPrizePercent = $lineaPrizePercent;

        return $this;
    }

    public function getBingoPrizePercent(): int
    {
        return $this->bingoPrizePercent;
    }

    public function setBingoPrizePercent(int $bingoPrizePercent): static
    {
        $this->bingoPrizePercent = $bingoPrizePercent;

        return $this;
    }

    public function getTotalPot(): float
    {
        return $this->cardPrice * $this->cards->count();
    }

    public function getLineaPrize(): float
    {
        return $this->getTotalPot() * ($this->lineaPrizePercent / 100);
    }

    public function getBingoPrize(): float
    {
        return $this->getTotalPot() * ($this->bingoPrizePercent / 100);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    /**
     * @return Collection<int, Card>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setGame($this);
        }

        return $this;
    }

    public function removeCard(Card $card): static
    {
        if ($this->cards->removeElement($card)) {
            if ($card->getGame() === $this) {
                $card->setGame(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Draw>
     */
    public function getDraws(): Collection
    {
        return $this->draws;
    }

    public function addDraw(Draw $draw): static
    {
        if (!$this->draws->contains($draw)) {
            $this->draws->add($draw);
            $draw->setGame($this);
        }

        return $this;
    }

    public function removeDraw(Draw $draw): static
    {
        if ($this->draws->removeElement($draw)) {
            if ($draw->getGame() === $this) {
                $draw->setGame(null);
            }
        }

        return $this;
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
            $claim->setGame($this);
        }

        return $this;
    }

    public function removeClaim(Claim $claim): static
    {
        if ($this->claims->removeElement($claim)) {
            if ($claim->getGame() === $this) {
                $claim->setGame(null);
            }
        }

        return $this;
    }
}
