<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SourceManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private SourceRepository $sourceRepository,
    ) {
    }

    public function findByFilename(string $filename): ?Source
    {
        return $this->sourceRepository->findOneByFilename($filename);
    }

    public function createFromDownloadedFile(string $filename, string $path, float $size): Source
    {
        $source = new Source();
        $source
            ->setFilename($filename)
            ->setFilepath($path)
            ->setSize($size);

        $this->em->persist($source);

        return $source;
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
