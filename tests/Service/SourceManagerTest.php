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
    public function testFindByFilenameDelegatesToRepository(): void
    {
        $source = new Source();

        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock(); /** @phpstan-ignore-line */
        $repository->expects($this->once())
            ->method('findOneByFilename')
            ->with('foo.mp4')
            ->willReturn($source);

        $em = $this->createMock(EntityManagerInterface::class);

        $manager = new SourceManager($em, $repository, $this->createMock(DownloadMetricManager::class));

        $this->assertSame($source, $manager->findByFilename('foo.mp4'));
    }

    public function testCreateFromDownloadedFilePersistsAndReturnsSource(): void
    {
        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock(); /** @phpstan-ignore-line */
        $em         = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Source $source): bool {
                return 'bar.mp4' === $source->getFilename()
                    && '/tmp' === $source->getFilepath()
                    && 123.4 === $source->getSize();
            }));

        $downloadMetricManager = $this->createMock(DownloadMetricManager::class);
        $downloadMetricManager->expects($this->once())->method('increment')->with(123.4);

        $manager = new SourceManager($em, $repository, $downloadMetricManager);

        $source = $manager->createFromDownloadedFile('bar.mp4', '/tmp', 123.4);

        $this->assertSame('bar.mp4', $source->getFilename());
        $this->assertSame('/tmp', $source->getFilepath());
        $this->assertSame(123.4, $source->getSize());
    }

    public function testFlushDelegatesToEntityManager(): void
    {
        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock(); /** @phpstan-ignore-line */
        $em         = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())->method('flush');

        $manager = new SourceManager($em, $repository, $this->createMock(DownloadMetricManager::class));
        $manager->flush();
    }
}
