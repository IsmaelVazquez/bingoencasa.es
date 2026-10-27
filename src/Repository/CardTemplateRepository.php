<?php

namespace App\Repository;

use App\Entity\CardTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardTemplate>
 */
class CardTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardTemplate::class);
    }

    /**
     * Take N available card templates and mark them as used.
     *
     * Like rooms, the pick and the flag change are a single statement with
     * FOR UPDATE SKIP LOCKED, so two games never share a template. Returns
     * fewer than N if the pool runs out: the caller must check and roll back.
     *
     * @return CardTemplate[]
     */
    public function getAvailableCards(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $sql = 'UPDATE card_template SET is_available = FALSE, last_used_at = NOW()
                WHERE is_available = TRUE AND id IN (
                    SELECT id FROM card_template WHERE is_available = TRUE
                    ORDER BY RANDOM() LIMIT :limit FOR UPDATE SKIP LOCKED
                )
                RETURNING id';
        $ids = $conn->executeQuery($sql, ['limit' => $count], ['limit' => ParameterType::INTEGER])->fetchFirstColumn();

        if (empty($ids)) {
            return [];
        }

        $cards = $this->findBy(['id' => $ids]);

        foreach ($cards as $card) {
            $card->markAsUsed();
        }

        return $cards;
    }

    /**
     * Count available card templates
     */
    public function countAvailable(): int
    {
        return $this->count(['isAvailable' => true]);
    }

    /**
     * Release all card templates (make them available again)
     */
    public function releaseAll(): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isAvailable', 'true')
            ->getQuery()
            ->execute();
    }

    /**
     * Release specific card templates by IDs
     */
    public function releaseByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isAvailable', 'true')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
