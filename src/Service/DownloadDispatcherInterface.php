<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

interface DownloadDispatcherInterface
{
    /**
     * @throws ExceptionInterface
     */
    public function dispatch(string $url, string $quality, string $telegramUserId = ''): DownloadTask;
}
