<?php

namespace App\Service;

use App\Entity\Room;
use App\Repository\CardTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class RoomReleaser
{
    public function __construct(
        private EntityManagerInterface $em,
        private CardTemplateRepository $cardTemplateRepository,
        private RoomCodeGenerator $codeGenerator,
    ) {}

    /**
     * Devuelve la sala al pool: libera los cartones de todas sus partidas,
     * borra los jugadores (con sus cartones y reclamaciones), retira el token
     * del anfitrión y cambia el PIN. Hay que hacer flush después.
     *
     * @return int número de cartones liberados
     */
    public function release(Room $room): int
    {
        $templateIds = [];
        foreach ($room->getGames() as $game) {
            foreach ($game->getCards() as $card) {
                $templateIds[] = $card->getSeed();
            }
        }

        if (!empty($templateIds)) {
            $this->cardTemplateRepository->releaseByIds($templateIds);
        }

        foreach ($room->getPlayers() as $player) {
            // Las reclamaciones apuntan al jugador y a su cartón: sin borrarlas
            // antes, el borrado del jugador choca con la clave foránea.
            foreach ($player->getClaims() as $claim) {
                $this->em->remove($claim);
            }
            $this->em->remove($player);
        }

        $room->setIsAvailable(true);
        $room->revokeHostToken();
        $room->setPin($this->codeGenerator->generatePin());

        return count($templateIds);
    }
}
