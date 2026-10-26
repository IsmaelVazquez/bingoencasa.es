<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
class Player
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\Column(length: 120)]
    private ?string $nickname = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Card>
     */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: Card::class, orphanRemoval: true)]
    private Collection $cards;

    /**
     * @var Collection<int, Claim>
     */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: Claim::class)]
    private Collection $claims;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->cards = new ArrayCollection();
        $this->claims = new ArrayCollection();
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

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public const NICKNAME_MAX_LENGTH = 30;

    /**
     * Limpia el apodo que escribe el jugador: sin caracteres de control,
     * espacios colapsados y como mucho NICKNAME_MAX_LENGTH caracteres.
     * Devuelve null si queda vacío.
     */
    public static function normalizeNickname(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $nickname = preg_replace('/[\p{C}]+/u', '', $raw) ?? '';
        $nickname = trim(preg_replace('/\s+/u', ' ', $nickname) ?? '');
        $nickname = mb_substr($nickname, 0, self::NICKNAME_MAX_LENGTH);

        return $nickname === '' ? null : $nickname;
    }

    public function setNickname(string $nickname): static
    {
        $this->nickname = $nickname;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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
            $card->setPlayer($this);
        }

        return $this;
    }

    public function removeCard(Card $card): static
    {
        if ($this->cards->removeElement($card)) {
            if ($card->getPlayer() === $this) {
                $card->setPlayer(null);
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
            $claim->setPlayer($this);
        }

        return $this;
    }

    public function removeClaim(Claim $claim): static
    {
        if ($this->claims->removeElement($claim)) {
            if ($claim->getPlayer() === $this) {
                $claim->setPlayer(null);
            }
        }

        return $this;
    }
}
