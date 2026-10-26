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
     * Get N available card templates and mark them as used
     */
    public function getAvailableCards(int $count): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id FROM card_template WHERE is_available = TRUE ORDER BY RANDOM() LIMIT :limit';
        $result = $conn->executeQuery($sql, ['limit' => $count], ['limit' => ParameterType::INTEGER]);
        $ids = array_column($result->fetchAllAssociative(), 'id');
        
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
