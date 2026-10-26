<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Get an available room and mark it as used
     */
    public function acquireAvailableRoom(): ?Room
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT id FROM room WHERE is_available = TRUE ORDER BY RANDOM() LIMIT 1';
        $result = $conn->executeQuery($sql);
        $row = $result->fetchAssociative();
        
        if (!$row) {
            return null;
        }
        
        $room = $this->find($row['id']);
        if ($room) {
            $room->acquire();
        }

        return $room;
    }

    /**
     * Count available rooms
     */
    public function countAvailable(): int
    {
        return $this->count(['isAvailable' => true]);
    }

    /**
     * Release a room back to the pool
     */
    public function releaseRoom(Room $room): void
    {
        $room->release();
    }

    /**
     * Release all rooms (make them available again)
     */
    public function releaseAll(): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.isAvailable', 'true')
            ->set('r.hostTokenHash', 'NULL')
            ->getQuery()
            ->execute();
    }

    /**
     * Find rooms that have been inactive for a given duration and release them
     */
    public function releaseStaleRooms(\DateTimeImmutable $olderThan): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.isAvailable', 'true')
            ->set('r.hostTokenHash', 'NULL')
            ->where('r.isAvailable = false')
            ->andWhere('r.lastUsedAt < :olderThan')
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->execute();
    }
}
