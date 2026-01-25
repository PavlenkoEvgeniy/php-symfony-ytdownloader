<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Service\SourceManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SourceManagerTest extends TestCase
{
    public function testFindByFilenameDelegatesToRepository(): void
    {
        $source = new Source();

        /** @phpstan-ignore-next-line */
        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock();
        $repository->expects($this->once())
            ->method('findOneByFilename')
            ->with('foo.mp4')
            ->willReturn($source);

        $em = $this->createMock(EntityManagerInterface::class);

        $manager = new SourceManager($em, $repository);

        $this->assertSame($source, $manager->findByFilename('foo.mp4'));
    }

    public function testCreateFromDownloadedFilePersistsAndReturnsSource(): void
    {
        /** @phpstan-ignore-next-line */
        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock();
        $em         = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Source $source): bool {
                return 'bar.mp4' === $source->getFilename()
                    && '/tmp' === $source->getFilepath()
                    && 123.4 === $source->getSize();
            }));

        $manager = new SourceManager($em, $repository);

        $source = $manager->createFromDownloadedFile('bar.mp4', '/tmp', 123.4);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertSame('bar.mp4', $source->getFilename());
        $this->assertSame('/tmp', $source->getFilepath());
        $this->assertSame(123.4, $source->getSize());
    }

    public function testFlushDelegatesToEntityManager(): void
    {
        /** @phpstan-ignore-next-line */
        $repository = $this->getMockBuilder(SourceRepository::class)
            ->disableOriginalConstructor()
            ->addMethods(['findOneByFilename'])
            ->getMock();
        $em         = $this->createMock(EntityManagerInterface::class);

        $em->expects($this->once())->method('flush');

        $manager = new SourceManager($em, $repository);
        $manager->flush();
    }
}
