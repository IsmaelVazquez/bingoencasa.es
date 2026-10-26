<?php

namespace App\Entity;

use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Index(columns: ['is_available'], name: 'idx_room_available')]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 32, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 4)]
    private ?string $pin = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column]
    private int $maxPlayers = 15;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /** SHA-256 del token del anfitrión actual; null mientras la sala está libre. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hostTokenHash = null;

    /**
     * @var Collection<int, Player>
     */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Player::class, orphanRemoval: true)]
    private Collection $players;

    /**
     * @var Collection<int, Game>
     */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Game::class, orphanRemoval: true)]
    private Collection $games;

    public function __construct()
    {
        $this->players = new ArrayCollection();
        $this->games = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Ulid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getPin(): ?string
    {
        return $this->pin;
    }

    public function setPin(string $pin): static
    {
        $this->pin = $pin;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Player>
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $player->setRoom($this);
        }

        return $this;
    }

    public function removePlayer(Player $player): static
    {
        if ($this->players->removeElement($player)) {
            if ($player->getRoom() === $this) {
                $player->setRoom(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Game>
     */
    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setRoom($this);
        }

        return $this;
    }

    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game)) {
            if ($game->getRoom() === $this) {
                $game->setRoom(null);
            }
        }

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

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;
        return $this;
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

    /**
     * Genera un token nuevo de anfitrión y guarda solo su hash.
     * Devuelve el token en claro, que no se vuelve a poder recuperar.
     */
    public function issueHostToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->hostTokenHash = hash('sha256', $token);

        return $token;
    }

    public function isHostToken(?string $token): bool
    {
        return $token !== null
            && $this->hostTokenHash !== null
            && hash_equals($this->hostTokenHash, hash('sha256', $token));
    }

    public function revokeHostToken(): static
    {
        $this->hostTokenHash = null;

        return $this;
    }

    public function isFull(): bool
    {
        return $this->players->count() >= $this->maxPlayers;
    }

    public function acquire(): static
    {
        $this->isAvailable = false;
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function release(): static
    {
        $this->isAvailable = true;
        $this->hostTokenHash = null;
        $this->players->clear();
        return $this;
    }
}
