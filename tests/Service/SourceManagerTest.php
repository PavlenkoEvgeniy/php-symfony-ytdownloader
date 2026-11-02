<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Service\DownloadMetricManager;
use App\Service\SourceManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SourceManagerTest extends TestCase
{
    /**
     * Verifies filename lookup delegates to the repository.
     */
    public function testFindByFilenameDelegatesToRepository(): void
    {
        $source = new Source();

        $repository = $this->createMock(SourceRepository::class);
        $repository->expects($this->once())
            ->method('findOneByFilename')
            ->with('foo.mp4')
            ->willReturn($source);

        $manager = new SourceManager(
            $this->createStub(EntityManagerInterface::class),
            $repository,
            /** @phpstan-ignore-next-line */
            $this->createStub(DownloadMetricManager::class),
        );

        $this->assertSame($source, $manager->findByFilename('foo.mp4'));
    }

    /**
     * Verifies a downloaded file is persisted as a source and counted in metrics.
     */
    public function testCreateFromDownloadedFilePersistsAndReturnsSource(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(static fn (Source $source): bool => 'bar.mp4' === $source->getFilename()
                && '/tmp' === $source->getFilepath()
                && 123.4 === $source->getSize()));

        $downloadMetricManager = $this->createMock(DownloadMetricManager::class);
        $downloadMetricManager->expects($this->once())->method('increment')->with(123.4);

        $manager = new SourceManager(
            $entityManager,
            /** @phpstan-ignore-next-line */
            $this->createStub(SourceRepository::class),
            $downloadMetricManager,
        );

        $source = $manager->createFromDownloadedFile('bar.mp4', '/tmp', 123.4);

        $this->assertSame('bar.mp4', $source->getFilename());
        $this->assertSame('/tmp', $source->getFilepath());
        $this->assertSame(123.4, $source->getSize());
    }

    /**
     * Verifies flush delegates to the entity manager.
     */
    public function testFlushDelegatesToEntityManager(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $manager = new SourceManager(
            $entityManager,
            /** @phpstan-ignore-next-line */
            $this->createStub(SourceRepository::class),
            /** @phpstan-ignore-next-line */
            $this->createStub(DownloadMetricManager::class),
        );

        $manager->flush();
    }
}
