<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Repository\SourceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class LibraryPruner implements LibraryPrunerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private EntityManagerInterface $entityManager,
        private AuditLoggerInterface $auditLogger,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function prune(\DateTimeImmutable $cutoff, bool $dryRun = false): array
    {
        $deleted = 0;
        $bytes   = 0;
        $failed  = 0;
        $files   = [];

        foreach ($this->sourceRepository->findOlderThan($cutoff) as $source) {
            $path      = \sprintf('%s/%s', $source->getFilepathOrFail(), $source->getFilenameOrFail());
            $size      = \is_file($path) ? (int) \filesize($path) : 0;
            $createdAt = $source->getCreatedAtOrFail();

            $files[] = [
                'name'      => $source->getFilenameOrFail(),
                'size'      => $size,
                'createdAt' => $createdAt,
            ];

            if ($dryRun) {
                $bytes += $size;

                continue;
            }

            if (\is_file($path)) {
                try {
                    if (!\unlink($path)) {
                        throw new \RuntimeException('unlink() returned false.');
                    }
                } catch (\Throwable $exception) {
                    ++$failed;

                    $this->logger->error('Failed to delete a library file', [
                        'path'  => $path,
                        'error' => $exception->getMessage(),
                    ]);

                    continue;
                }

                $bytes += $size;
            }

            $this->entityManager->remove($source);
            ++$deleted;

            $this->logger->info('Deleted a library file', ['path' => $path, 'size' => $size]);
        }

        if (!$dryRun) {
            $this->entityManager->flush();

            if ($deleted > 0 || $failed > 0) {
                $this->auditLogger->log(
                    action: AuditAction::LIBRARY_PRUNE,
                    channel: AuditChannel::SYSTEM,
                    targetType: 'source',
                    targetLabel: \sprintf('older than %s', $cutoff->format('Y-m-d H:i:s')),
                    metadata: [
                        'deleted' => $deleted,
                        'failed'  => $failed,
                        'bytes'   => $bytes,
                        'cutoff'  => $cutoff->format(DATE_ATOM),
                    ],
                    actorType: AuditActorType::SYSTEM,
                );
            }
        }

        return [
            'deleted' => $deleted,
            'bytes'   => $bytes,
            'failed'  => $failed,
            'files'   => $files,
        ];
    }
}
