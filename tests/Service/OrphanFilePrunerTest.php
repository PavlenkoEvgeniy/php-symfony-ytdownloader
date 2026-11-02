<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\SourceRepository;
use App\Service\OrphanFilePruner;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OrphanFilePrunerTest extends TestCase
{
    private string $downloadsDir;

    protected function setUp(): void
    {
        $this->downloadsDir = \sys_get_temp_dir() . '/orphan-pruner-' . \bin2hex(\random_bytes(6));
        \mkdir($this->downloadsDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->downloadsDir);
    }

    public function testFindOrphansReturnsOnlyOldFilesWithoutSource(): void
    {
        $oldTimestamp = \time() - 7200;

        $keptPath   = $this->downloadsDir . '/kept.mp4';
        $freshPath  = $this->downloadsDir . '/fresh.part';
        $orphanPath = $this->downloadsDir . '/orphan.part';

        \file_put_contents($keptPath, 'kept');
        \file_put_contents($freshPath, 'fresh');
        \file_put_contents($orphanPath, 'orphan');
        \touch($keptPath, $oldTimestamp);
        \touch($orphanPath, $oldTimestamp);

        $orphans = $this->createPruner(['kept.mp4'])->findOrphans();

        $this->assertCount(1, $orphans);
        $this->assertSame('orphan.part', $orphans[0]['name']);
        $this->assertSame($orphanPath, $orphans[0]['path']);
        $this->assertSame(6, $orphans[0]['size']);
        $this->assertSame($oldTimestamp, $orphans[0]['modifiedAt']->getTimestamp());

        $this->assertFileExists($orphanPath);
    }

    public function testPruneDeletesOnlyOrphans(): void
    {
        $oldTimestamp = \time() - 7200;

        $keptPath   = $this->downloadsDir . '/kept.mp4';
        $freshPath  = $this->downloadsDir . '/fresh.part';
        $orphanPath = $this->downloadsDir . '/orphan.part';

        \file_put_contents($keptPath, 'kept');
        \file_put_contents($freshPath, 'fresh');
        \file_put_contents($orphanPath, 'orphan');
        \touch($keptPath, $oldTimestamp);
        \touch($orphanPath, $oldTimestamp);

        $result = $this->createPruner(['kept.mp4'])->prune();

        $this->assertSame(['deleted' => 1, 'bytes' => 6, 'failed' => 0], $result);
        $this->assertFileDoesNotExist($orphanPath);
        $this->assertFileExists($keptPath);
        $this->assertFileExists($freshPath);
    }

    public function testFindOrphansIgnoresDirectories(): void
    {
        \mkdir($this->downloadsDir . '/nested');

        $this->assertSame([], $this->createPruner([])->findOrphans());
    }

    /**
     * @param list<string> $referencedFilenames
     */
    private function createPruner(array $referencedFilenames): OrphanFilePruner
    {
        /** @phpstan-ignore-next-line */
        $sourceRepository = $this->createMock(SourceRepository::class);
        $sourceRepository->method('findAllFilenames')->willReturn($referencedFilenames);

        return new OrphanFilePruner(
            $sourceRepository,
            $this->createMock(LoggerInterface::class),
            $this->downloadsDir,
            3600,
        );
    }

    private function removeDirectory(string $directory): void
    {
        $entries = \scandir($directory);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (\is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            \unlink($path);
        }

        \rmdir($directory);
    }
}
