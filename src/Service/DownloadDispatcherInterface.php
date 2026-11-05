<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Exception\DownloadIntakeDisabledException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

interface DownloadDispatcherInterface
{
    /**
     * @throws ExceptionInterface
     * @throws DownloadIntakeDisabledException
     */
    public function dispatch(
        string $url,
        string $quality,
        ?User $user = null,
        string $origin = DownloadTask::ORIGIN_UI,
        ?int $telegramChatId = null,
        ?string $title = null,
    ): DownloadTask;
}
