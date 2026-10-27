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
     * Get an available room and mark it as used.
     *
     * The pick and the flag change go in a single statement with
     * FOR UPDATE SKIP LOCKED, so two hosts creating a room at the same time
     * never get the same one.
     */
    public function acquireAvailableRoom(): ?Room
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'UPDATE room SET is_available = FALSE, last_used_at = NOW()
                WHERE is_available = TRUE AND id = (
                    SELECT id FROM room WHERE is_available = TRUE
                    ORDER BY RANDOM() LIMIT 1 FOR UPDATE SKIP LOCKED
                )
                RETURNING id';

        // A room taken by another transaction between the snapshot and the
        // lock leaves the subquery empty: try again a few times.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $id = $conn->executeQuery($sql)->fetchOne();
            if ($id !== false) {
                $room = $this->find($id);
                $room?->acquire();

                return $room;
            }
            if ($this->countAvailable() === 0) {
                return null;
            }
        }

        return null;
    }

    /**
     * Rooms in use whose last activity is older than the given date.
     *
     * @return Room[]
     */
    public function findStaleRooms(\DateTimeImmutable $olderThan): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isAvailable = false')
            ->andWhere('r.lastUsedAt IS NULL OR r.lastUsedAt < :olderThan')
            ->setParameter('olderThan', $olderThan)
            ->getQuery()
            ->getResult();
    }

    /** @return Room[] */
    public function findInUse(): array
    {
        return $this->findBy(['isAvailable' => false]);
    }

    /**
     * Count available rooms
     */
    public function countAvailable(): int
    {
        return $this->count(['isAvailable' => true]);
    }
}
