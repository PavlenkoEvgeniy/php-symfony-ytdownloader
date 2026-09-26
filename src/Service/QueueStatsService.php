<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Repository\DownloadTaskRepository;
use App\Repository\SourceRepository;

final readonly class QueueStatsService
{
    public function __construct(
        private DownloadTaskRepository $downloadTaskRepository,
        private SourceRepository $sourceRepository,
    ) {
    }

    /**
     * @return array{
     *     queued: int,
     *     processing: int,
     *     success: int,
     *     error: int,
     *     totalSize: int,
     *     tasks: list<array{id: ?int, url: ?string, quality: ?string, status: ?string, createdAt: ?string}>
     * }
     */
    public function getStats(bool $withTasks = true): array
    {
        $counts = $this->downloadTaskRepository->getStatusCounts();

        $tasks = [];
        if ($withTasks) {
            $tasks = \array_map(
                static fn (DownloadTask $task): array => [
                    'id'        => $task->getId(),
                    'url'       => $task->getUrl(),
                    'quality'   => $task->getQuality(),
                    'status'    => $task->getStatus(),
                    'createdAt' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ],
                $this->downloadTaskRepository->getActiveTasks()
            );
        }

        return [
            'queued'     => $counts[DownloadTask::STATUS_QUEUED],
            'processing' => $counts[DownloadTask::STATUS_PROCESSING],
            'success'    => $counts[DownloadTask::STATUS_SUCCESS],
            'error'      => $counts[DownloadTask::STATUS_ERROR],
            'totalSize'  => $this->sourceRepository->getTotalSize(),
            'tasks'      => $tasks,
        ];
    }
}
