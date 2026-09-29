<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Repository\DownloadTaskRepository;

final readonly class QueueStatsService
{
    public function __construct(
        private DownloadTaskRepository $downloadTaskRepository,
        private DownloadMetricManager $downloadMetricManager,
    ) {
    }

    /**
     * @return array{
     *     queued: int,
     *     processing: int,
     *     success: int,
     *     error: int,
     *     totalSize: int
     * }
     */
    public function getStats(): array
    {
        $counts = $this->downloadTaskRepository->getStatusCounts();

        return [
            'queued'     => $counts[DownloadTask::STATUS_QUEUED],
            'processing' => $counts[DownloadTask::STATUS_PROCESSING],
            'success'    => $counts[DownloadTask::STATUS_SUCCESS],
            'error'      => $counts[DownloadTask::STATUS_ERROR],
            'totalSize'  => (int) $this->downloadMetricManager->getTotal(),
        ];
    }
}
