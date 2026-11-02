<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditEvent>
 */
final class AuditEventRepository extends ServiceEntityRepository implements AuditEventRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, AuditEvent::class);
    }

    #[\Override]
    public function countOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.occurredAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    #[\Override]
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->where('a.occurredAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute()
        ;
    }
}
