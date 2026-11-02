<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 *
 * @method findOneByFilename(string $filename)
 */
final class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * Filenames of every stored source, used to tell kept files from orphans.
     *
     * @return list<string>
     */
    public function findAllFilenames(): array
    {
        /** @var list<mixed> $filenames */
        $filenames = $this->createQueryBuilder('s')
            ->select('s.filename')
            ->getQuery()
            ->getSingleColumnResult();

        return \array_map(static fn (mixed $filename): string => (string) $filename, $filenames);
    }
}
