<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    public function getTotalProcessingCount(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.type = :type')
            ->setParameter('type', 'processing')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalInProgressCount(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.type = :type')
            ->setParameter('type', 'in progress')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalSuccessCount(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.type = :type')
            ->setParameter('type', 'success')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalErrorCount(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.type = :type')
            ->setParameter('type', 'error')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMaxSize(): ?int
    {
        $result = $this->createQueryBuilder('l')
            ->select('MAX(l.size) as maxSize')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $result ? (int) $result : null;
    }

    public function getTotalSize(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('SUM(l.size) as totalSize')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Log[] Returns an array of Log objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Log
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
