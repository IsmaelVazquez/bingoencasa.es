<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findOneByUlid(string $id): ?Game
    {
        try {
            $ulid = Ulid::fromBase32($id);
            return $this->find($ulid);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Huella de lo que ven los jugadores al sondear: estado, objetivo, bolas
     * y reclamaciones. Cambia en cuanto cambia cualquiera de ellos.
     */
    public function pollSignature(Game $game): string
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<'SQL'
                SELECT g.status, g.prize_target,
                       (SELECT COUNT(*) || ':' || COALESCE(MAX(d.id::text), '') FROM draw d WHERE d.game_id = g.id) AS draws,
                       (SELECT STRING_AGG(c.id::text || ':' || c.status, ',' ORDER BY c.id) FROM claim c WHERE c.game_id = g.id) AS claims
                FROM game g
                WHERE g.id = :id
                SQL,
            ['id' => $game->getId()->toRfc4122()]
        );

        return md5(json_encode($row));
    }
}
