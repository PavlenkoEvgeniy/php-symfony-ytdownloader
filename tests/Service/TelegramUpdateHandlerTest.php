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
     * Verifies /help sends the collapsible command menu.
     */
    public function testHelpSendsMainMenu(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(
                42,
                $this->stringContains('/start'),
                $this->callback(static function (array $markup): bool {
                    $labels = \array_map(
                        static fn (array $row): array => \array_map(static fn (array $button): string => $button['text'], $row),
                        $markup['keyboard'],
                    );

                    return [
                        ['/start', '/login'],
                        ['/logout', '/cancel'],
                        ['/status', '/help'],
                    ] === $labels
                        && true === $markup['resize_keyboard']
                        && false === $markup['is_persistent'];
                }),
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
