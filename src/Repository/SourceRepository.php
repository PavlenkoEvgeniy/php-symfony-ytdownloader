<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadTask;
use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
final class SourceRepository extends ServiceEntityRepository implements SourceRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Source::class);
    }

    #[\Override]
    public function findOneByFilename(string $filename): ?Source
    {
        $source = $this->createQueryBuilder('s')
            ->where('s.filename = :filename')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $source instanceof Source ? $source : null;
    }

    #[\Override]
    /**
     * @return Source[]
     */
    public function findByDownloadTask(DownloadTask $downloadTask): array
    {
        /** @var Source[] $sources */
        $sources = $this->createQueryBuilder('s')
            ->where('s.downloadTask = :downloadTask')
            ->setParameter('downloadTask', $downloadTask)
            ->orderBy('s.id')
            ->getQuery()
            ->getResult()
        ;

        return $sources;
    }

    #[\Override]
    /**
     * Sources older than the cutoff; sources of active tasks are skipped so a
     * running download cannot lose a file the worker may still need.
     *
     * @return Source[]
     */
    public function findOlderThan(\DateTimeImmutable $cutoff, bool $skipActiveTasks = true): array
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->where('s.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('s.createdAt')
        ;

        if ($skipActiveTasks) {
            $queryBuilder
                ->leftJoin('s.downloadTask', 't')
                ->andWhere('t IS NULL OR t.status NOT IN (:activeStatuses)')
                ->setParameter('activeStatuses', [
                    DownloadTask::STATUS_QUEUED,
                    DownloadTask::STATUS_PROCESSING,
                ], ArrayParameterType::STRING)
            ;
        }

        /** @var Source[] $sources */
        $sources = $queryBuilder->getQuery()->getResult();

        return $sources;
    }

    #[\Override]
    /**
     * @return Source[]
     */
    public function findAllSources(): array
    {
        /** @var Source[] $sources */
        $sources = $this->createQueryBuilder('s')
            ->getQuery()
            ->getResult()
        ;

        return $sources;
    }

    #[\Override]
    /**
     * @return Source[]
     */
    public function findAllOrderedByCreatedAt(string $order): array
    {
        /** @var Source[] $sources */
        $sources = $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', $order)
            ->getQuery()
            ->getResult()
        ;

        return $sources;
    }

    #[\Override]
    /**
     * Filenames of every stored source, used to tell kept files from orphans.
     *
     * @return string[]
     */
    public function findAllFilenames(): array
    {
        /** @var mixed[] $filenames */
        $filenames = $this->createQueryBuilder('s')
            ->select('s.filename')
            ->getQuery()
            ->getSingleColumnResult();

        return \array_map(static fn (mixed $filename): string => (string) $filename, $filenames);
    }
}
