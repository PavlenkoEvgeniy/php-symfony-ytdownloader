<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SourceRepository;
use Psr\Log\LoggerInterface;

/**
 * Finds and deletes orphan files: files in the downloads directory that no Source
 * references and that no download is currently writing (see CONTEXT.md "Orphan File").
 */
final readonly class OrphanFilePruner
{
    public function __construct(
        private SourceRepository $sourceRepository,
        private LoggerInterface $logger,
        private string $downloadsDir,
        private int $orphanTtlSeconds,
    ) {
    }

    /**
     * @return list<array{name: string, path: string, size: int, modifiedAt: \DateTimeImmutable}>
     */
    public function findOrphans(): array
    {
        $referenced = \array_flip($this->sourceRepository->findAllFilenames());
        $threshold  = \time() - $this->orphanTtlSeconds;
        $orphans    = [];

        foreach ($this->filesInDownloadsDir() as $file) {
            $name = $file->getFilename();

            if (isset($referenced[$name])) {
                continue;
            }

            $modifiedAt = $file->getMTime();

            // A fresh file may still be written by a worker: keep it until it is older than the TTL.
            if (false === $modifiedAt || $modifiedAt > $threshold) {
                continue;
            }

            $size = $file->getSize();

            $orphans[] = [
                'name'       => $name,
                'path'       => $file->getPathname(),
                'size'       => false === $size ? 0 : $size,
                'modifiedAt' => (new \DateTimeImmutable())->setTimestamp($modifiedAt),
            ];
        }

        \usort(
            $orphans,
            static fn (array $left, array $right): int => $left['modifiedAt']->getTimestamp() <=> $right['modifiedAt']->getTimestamp(),
        );

        return $orphans;
    }

    /**
     * @return array{deleted: int, bytes: int, failed: int}
     */
    public function prune(): array
    {
        $deleted = 0;
        $bytes   = 0;
        $failed  = 0;

        foreach ($this->findOrphans() as $orphan) {
            try {
                if (!\unlink($orphan['path'])) {
                    throw new \RuntimeException('unlink() returned false.');
                }
            } catch (\Throwable $e) {
                ++$failed;

                $this->logger->error('Failed to prune orphan file', [
                    'path'  => $orphan['path'],
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            ++$deleted;
            $bytes += $orphan['size'];

            $this->logger->info('Pruned orphan file', [
                'path' => $orphan['path'],
                'size' => $orphan['size'],
            ]);
        }

        return ['deleted' => $deleted, 'bytes' => $bytes, 'failed' => $failed];
    }

    /**
     * @return \Generator<int, \SplFileInfo>
     */
    private function filesInDownloadsDir(): \Generator
    {
        if (!\is_dir($this->downloadsDir)) {
            return;
        }

        $entries = \scandir($this->downloadsDir);

        if (false === $entries) {
            return;
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $this->downloadsDir . DIRECTORY_SEPARATOR . $entry;

            // Only files are pruned; directories and broken links are left for a human.
            if (!\is_file($path)) {
                continue;
            }

            yield new \SplFileInfo($path);
        }
    }
}
