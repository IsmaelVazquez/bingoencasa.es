<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * Jugadores con cartón en la partida, en una sola consulta.
     *
     * @return Player[]
     */
    public function findWithCardsInGame(Game $game): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.cards', 'c')
            ->where('c.game = :game')
            ->setParameter('game', $game->getId(), 'ulid')
            ->groupBy('p.id')
            ->orderBy('p.id')
            ->getQuery()
            ->getResult();
    }
}
