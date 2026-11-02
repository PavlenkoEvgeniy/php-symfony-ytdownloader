<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finds and deletes orphan files: files in the downloads directory that no Source
 * references and that no download is currently writing (see CONTEXT.md "Orphan File").
 */
final readonly class OrphanFilePruner implements OrphanFilePrunerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private LoggerInterface $logger,
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private string $downloadsDir,
        #[Autowire('%app.downloads.orphan_ttl_seconds%')]
        private int $orphanTtlSeconds,
    ) {
    }

    #[\Override]
    /**
     * @return array{name: string, path: string, size: int, modifiedAt: \DateTimeImmutable}[]
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
                'modifiedAt' => new \DateTimeImmutable()->setTimestamp($modifiedAt),
            ];
        }

        \usort(
            $orphans,
            static fn (array $left, array $right): int => $left['modifiedAt']->getTimestamp() <=> $right['modifiedAt']->getTimestamp(),
        );

        return $orphans;
    }

    #[\Override]
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
            } catch (\Throwable $exception) {
                ++$failed;

                $this->logger->error('Failed to prune orphan file', [
                    'path'  => $orphan['path'],
                    'error' => $exception->getMessage(),
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

            $path = \sprintf('%s%s%s', $this->downloadsDir, DIRECTORY_SEPARATOR, $entry);

            // Only files are pruned; directories and broken links are left for a human.
            if (!\is_file($path)) {
                continue;
            }

            yield new \SplFileInfo($path);
        }
    }
}
