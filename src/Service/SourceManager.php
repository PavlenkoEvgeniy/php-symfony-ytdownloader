<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Repository\SourceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SourceManager implements SourceManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SourceRepositoryInterface $sourceRepository,
        private DownloadMetricManagerInterface $downloadMetricManager,
    ) {
    }

    #[\Override]
    public function findByFilename(string $filename): ?Source
    {
        return $this->sourceRepository->findOneByFilename($filename);
    }

    #[\Override]
    public function createFromDownloadedFile(string $filename, string $path, float $size): Source
    {
        $source = new Source()->setFilename($filename)
            ->setFilepath($path)
            ->setSize($size)
        ;

        $this->entityManager->persist($source);
        $this->downloadMetricManager->increment($size);

        return $source;
    }

    #[\Override]
    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
