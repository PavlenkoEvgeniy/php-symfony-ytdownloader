<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Message\DownloadMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DownloadDispatcher implements DownloadDispatcherInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[\Override]
    /**
     * Persists the task so it is visible as "queued" and only then
     * dispatches the message, so every dispatch has a matching task row.
     *
     * @throws ExceptionInterface
     */
    public function dispatch(string $url, string $quality, string $telegramUserId = ''): DownloadTask
    {
        $task = new DownloadTask()->setUrl($url)
            ->setQuality($quality)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new DownloadMessage($url, $quality, $telegramUserId, $task->getId()));

        return $task;
    }
}
