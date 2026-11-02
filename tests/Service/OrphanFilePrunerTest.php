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
        $this->downloadsDir = \sprintf('%s/orphan-pruner-%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(6)));
        \mkdir($this->downloadsDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->downloadsDir);
    }

    /**
     * Verifies only old orphan files are reported.
     */
    public function testFindOrphansReturnsOnlyOldFilesWithoutSource(): void
    {
        $oldTimestamp = \time() - 7200;

        $keptPath   = \sprintf('%s/kept.mp4', $this->downloadsDir);
        $freshPath  = \sprintf('%s/fresh.part', $this->downloadsDir);
        $orphanPath = \sprintf('%s/orphan.part', $this->downloadsDir);

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

    /**
     * Verifies pruning deletes old orphan files and keeps the rest.
     */
    public function testPruneDeletesOnlyOrphans(): void
    {
        $oldTimestamp = \time() - 7200;

        $keptPath   = \sprintf('%s/kept.mp4', $this->downloadsDir);
        $freshPath  = \sprintf('%s/fresh.part', $this->downloadsDir);
        $orphanPath = \sprintf('%s/orphan.part', $this->downloadsDir);

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

    /**
     * Verifies directories in the downloads folder are not treated as orphans.
     */
    public function testFindOrphansIgnoresDirectories(): void
    {
        \mkdir(\sprintf('%s/nested', $this->downloadsDir));

        $this->assertSame([], $this->createPruner([])->findOrphans());
    }

    /**
     * @param string[] $referencedFilenames
     */
    private function createPruner(array $referencedFilenames): OrphanFilePruner
    {
        /** @phpstan-ignore-next-line */
        $sourceRepository = $this->createStub(SourceRepository::class);
        $sourceRepository->method('findAllFilenames')->willReturn($referencedFilenames);

        return new OrphanFilePruner(
            $sourceRepository,
            $this->createStub(LoggerInterface::class),
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

            $path = \sprintf('%s/%s', $directory, $entry);

            if (\is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            \unlink($path);
        }

        \rmdir($directory);
    }
}
