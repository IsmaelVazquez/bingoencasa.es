<?php

namespace App\Service;

use App\Entity\Player;
use App\Entity\Room;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Quién puede tocar qué en el Modo Fiesta.
 *
 * No hay cuentas: el anfitrión y los jugadores se identifican con la sesión
 * del navegador. Al crear la sala, el anfitrión recibe un token que solo vive
 * en su sesión (en la base de datos queda el hash). Al unirse, el jugador
 * queda apuntado en la sesión de su dispositivo. Los identificadores que
 * viajan en la API (partida, jugador, reclamación) no dan acceso por sí solos.
 */
class FiestaAccess
{
    private const HOST_TOKENS = 'fiesta_host_tokens';
    private const PLAYERS = 'fiesta_players';

    public function __construct(
        private RequestStack $requestStack,
    ) {}

    /** Marca la sesión actual como anfitriona de la sala. Hay que hacer flush después. */
    public function grantHost(Room $room): void
    {
        $session = $this->requestStack->getSession();
        $tokens = $session->get(self::HOST_TOKENS, []);
        $tokens[$room->getId()->toBase32()] = $room->issueHostToken();
        $session->set(self::HOST_TOKENS, $tokens);
    }

    public function isHost(?Room $room): bool
    {
        if (!$room || $room->isAvailable()) {
            return false;
        }

        $tokens = $this->requestStack->getSession()->get(self::HOST_TOKENS, []);

        return $room->isHostToken($tokens[$room->getId()->toBase32()] ?? null);
    }

    public function grantPlayer(Player $player): void
    {
        $session = $this->requestStack->getSession();
        $players = $session->get(self::PLAYERS, []);
        $players[$player->getId()->toBase32()] = true;
        $session->set(self::PLAYERS, $players);
    }

    public function isPlayer(?Player $player): bool
    {
        if (!$player) {
            return false;
        }

        $players = $this->requestStack->getSession()->get(self::PLAYERS, []);

        return isset($players[$player->getId()->toBase32()]);
    }

    /** El anfitrión o cualquier jugador de la sala. */
    public function canSeeRoom(Room $room): bool
    {
        if ($this->isHost($room)) {
            return true;
        }

        foreach ($room->getPlayers() as $player) {
            if ($this->isPlayer($player)) {
                return true;
            }
        }

        return false;
    }
}
