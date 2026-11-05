<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\DownloadDispatcherInterface;
use App\Service\TelegramClientInterface;
use App\Service\TelegramUpdateHandler;
use App\Service\YoutubeDlProbeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
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

    /**
     * Verifies a Telegram download is refused when the disk is under pressure.
     */
    public function testCallbackRejectsDownloadUnderDiskPressure(): void
    {
        $token = \str_repeat('a', 32);

        $item = $this->createStub(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn([
            'token'            => $token,
            'url'              => 'https://test.local/video',
            'title'            => 'Test',
            'telegram_user_id' => 42,
        ]);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_pending_42')->willReturn($item);
        $cache->expects($this->once())->method('deleteItem')->with('telegram_pending_42');

        $user = new User();
        $user->setTelegramUserId(42)->setIsEnabled(true);

        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $userRepository->method('findOneByTelegramUserId')->willReturn($user);

        $dispatcher = $this->createStub(DownloadDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(InsufficientDiskSpaceException::create(0.0, 1));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', $this->stringContains('free disk space'));
        $client->expects($this->never())->method('editMessageText');

        $handler = new TelegramUpdateHandler(
            $userRepository,
            $this->createStub(DownloadTaskRepositoryInterface::class),
            $dispatcher,
            $client,
            $this->createStub(YoutubeDlProbeInterface::class),
            $cache,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            'https://test.local',
        );

        $handler->handle([
            'callback_query' => [
                'id'      => 'callback-1',
                'data'    => \sprintf('dl:%s:best', $token),
                'from'    => ['id' => 42],
                'message' => [
                    'message_id' => 7,
                    'chat'       => ['id' => 42],
                ],
            ],
        ]);
    }
}
