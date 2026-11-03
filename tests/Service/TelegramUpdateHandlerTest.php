<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\DownloadDispatcherInterface;
use App\Service\TelegramClientInterface;
use App\Service\TelegramUpdateHandler;
use App\Service\YoutubeDlProbeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class TelegramUpdateHandlerTest extends TestCase
{
    /**
     * Verifies /help lists the commands and keeps the reply keyboard hidden.
     */
    public function testHelpRemovesKeyboard(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(
                42,
                $this->stringContains('/start'),
                ['remove_keyboard' => true],
            );

        $handler = new TelegramUpdateHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $this->createStub(DownloadDispatcherInterface::class),
            $client,
            $this->createStub(YoutubeDlProbeInterface::class),
            $this->createStub(CacheItemPoolInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            'https://test.local',
        );

        $handler->handle([
            'message' => [
                'message_id' => 1,
                'chat'       => ['id' => 42, 'type' => 'private'],
                'from'       => ['id' => 42],
                'text'       => '/help',
            ],
        ]);
    }
}
