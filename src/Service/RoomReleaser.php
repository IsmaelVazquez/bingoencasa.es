<?php

namespace App\Service;

use App\Entity\Room;
use App\Enum\GameStatus;
use App\Repository\CardTemplateRepository;
use App\Repository\RoomRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class RoomReleaser
{
    public function __construct(
        private EntityManagerInterface $em,
        private CardTemplateRepository $cardTemplateRepository,
        private RoomRepository $roomRepository,
        private RoomCodeGenerator $codeGenerator,
    ) {}

    /**
     * Libera las salas sin actividad desde la fecha dada. Cada sala va en su
     * propia transacción y se vuelve a comprobar con la fila bloqueada, por si
     * el anfitrión la ha usado mientras tanto. Hace flush.
     *
     * @return int número de salas liberadas
     */
    public function releaseStale(\DateTimeImmutable $olderThan): int
    {
        $released = 0;
        foreach ($this->roomRepository->findStaleRooms($olderThan) as $room) {
            $this->em->wrapInTransaction(function () use ($room, $olderThan, &$released): void {
                $this->em->refresh($room, LockMode::PESSIMISTIC_WRITE);
                $lastUsedAt = $room->getLastUsedAt();
                if ($room->isAvailable() || ($lastUsedAt !== null && $lastUsedAt >= $olderThan)) {
                    return;
                }
                $this->release($room);
                $released++;
            });
        }

        return $released;
    }

    /**
     * Libera todas las salas ocupadas, con la misma limpieza que una a una.
     * Hace flush.
     *
     * @return int número de salas liberadas
     */
    public function releaseAll(): int
    {
        $rooms = $this->roomRepository->findInUse();

        $this->em->wrapInTransaction(function () use ($rooms): void {
            foreach ($rooms as $room) {
                $this->release($room);
            }
        });

        return count($rooms);
    }

    /**
     * Devuelve la sala al pool: libera los cartones de las partidas sin
     * terminar, borra las partidas y los jugadores (con sus cartones y
     * reclamaciones), retira el token del anfitrión y cambia el PIN. Hay que
     * hacer flush después.
     *
     * Las partidas FINALIZADA ya devolvieron sus cartones al terminar, y
     * puede que otra sala los esté usando: no se vuelven a liberar.
     *
     * @return int número de cartones liberados
     */
    public function release(Room $room): int
    {
        $templateIds = [];
        foreach ($room->getGames() as $game) {
            if ($game->getStatus() !== GameStatus::FINALIZADA->value) {
                foreach ($game->getCards() as $card) {
                    $templateIds[] = $card->getSeed();
                }
            }
        }

        if (!empty($templateIds)) {
            $this->cardTemplateRepository->releaseByIds($templateIds);
        }

        foreach ($room->getGames() as $game) {
            // Borrar la partida arrastra sus cartones, bolas y reclamaciones
            // (orphanRemoval). Sin esto, el siguiente anfitrión de la sala
            // vería la última partida del anterior.
            $this->em->remove($game);
        }

        foreach ($room->getPlayers() as $player) {
            // Las reclamaciones apuntan al jugador y a su cartón: sin borrarlas
            // antes, el borrado del jugador choca con la clave foránea.
            foreach ($player->getClaims() as $claim) {
                $this->em->remove($claim);
            }
            $this->em->remove($player);
        }

        $room->getGames()->clear();
        $room->getPlayers()->clear();
        $room->setIsAvailable(true);
        $room->revokeHostToken();
        $room->setPin($this->codeGenerator->generatePin());

        return count($templateIds);
    }
}
