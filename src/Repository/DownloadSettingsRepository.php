<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DownloadSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadSettings>
 */
final class DownloadSettingsRepository extends ServiceEntityRepository implements DownloadSettingsRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, DownloadSettings::class);
    }

    #[\Override]
    public function getSettings(): DownloadSettings
    {
        $settings = $this->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $settings instanceof DownloadSettings ? $settings : new DownloadSettings();
    }
}
