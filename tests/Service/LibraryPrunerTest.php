<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Source;
use App\Repository\SourceRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\LibraryPruner;
use App\Tests\Support\FakeDownloadsStreamWrapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LibraryPrunerTest extends TestCase
{
    private string $downloadsDir;

    protected function setUp(): void
    {
        $this->downloadsDir = \sprintf('%s/library-pruner-%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(6)));
        \mkdir($this->downloadsDir);
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->downloadsDir . '/*') ?: [] as $file) {
            @\unlink($file);
        }

        @\rmdir($this->downloadsDir);
    }

    /**
     * Verifies an old source and its file are deleted.
     */
    public function testPruneDeletesOldSourceAndFile(): void
    {
        $path = \sprintf('%s/old.mp4', $this->downloadsDir);
        \file_put_contents($path, 'test');

        $source = $this->source('old.mp4', $this->downloadsDir);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($source);
        $entityManager->expects($this->once())->method('flush');

        $audit = $this->createMock(AuditLoggerInterface::class);
        $audit->expects($this->once())->method('log');

        $result = $this->pruner([$source], $entityManager, $audit)->prune(new \DateTimeImmutable('-1 day'));

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(4, $result['bytes']);
        $this->assertSame(0, $result['failed']);
        $this->assertCount(1, $result['files']);
        $this->assertFileDoesNotExist($path);
    }

    /**
     * Verifies a dry run reports files without deleting them.
     */
    public function testDryRunKeepsTheFile(): void
    {
        $path = \sprintf('%s/old.mp4', $this->downloadsDir);
        \file_put_contents($path, 'test');

        $source = $this->source('old.mp4', $this->downloadsDir);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');
        $entityManager->expects($this->never())->method('flush');

        $audit = $this->createMock(AuditLoggerInterface::class);
        $audit->expects($this->never())->method('log');

        $result = $this->pruner([$source], $entityManager, $audit)->prune(new \DateTimeImmutable('-1 day'), true);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(4, $result['bytes']);
        $this->assertCount(1, $result['files']);
        $this->assertFileExists($path);
    }

    /**
     * Verifies a stale row is removed even when the file is already gone.
     */
    public function testMissingFileIsRemovedFromTheLibrary(): void
    {
        $source = $this->source('missing.mp4', $this->downloadsDir);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($source);
        $entityManager->expects($this->once())->method('flush');

        $result = $this->pruner([$source], $entityManager, $this->createStub(AuditLoggerInterface::class))
            ->prune(new \DateTimeImmutable('-1 day'));

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(0, $result['bytes']);
        $this->assertSame(0, $result['failed']);
    }

    /**
     * Verifies a failed unlink keeps the row and reports the failure.
     */
    public function testUnlinkFailureKeepsTheSource(): void
    {
        FakeDownloadsStreamWrapper::register();

        try {
            $source = $this->source('orphan.part', FakeDownloadsStreamWrapper::url('readable'));

            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager->expects($this->never())->method('remove');
            $entityManager->expects($this->once())->method('flush');

            $audit = $this->createMock(AuditLoggerInterface::class);
            $audit->expects($this->once())->method('log');

            $result = $this->pruner([$source], $entityManager, $audit)->prune(new \DateTimeImmutable('-1 day'));

            $this->assertSame(0, $result['deleted']);
            $this->assertSame(1, $result['failed']);
        } finally {
            FakeDownloadsStreamWrapper::unregister();
        }
    }

    /**
     * @param Source[] $sources
     */
    private function pruner(
        array $sources,
        EntityManagerInterface $entityManager,
        AuditLoggerInterface $audit,
    ): LibraryPruner {
        $repository = $this->createStub(SourceRepositoryInterface::class);
        $repository->method('findOlderThan')->willReturn($sources);

        return new LibraryPruner($repository, $entityManager, $audit, $this->createStub(LoggerInterface::class));
    }

    private function source(string $filename, string $filepath): Source
    {
        return (new Source())->setFilename($filename)
            ->setFilepath($filepath)
            ->setSize(4.0)
            ->setCreatedAt(new \DateTime('-2 days'))
        ;
    }
}
