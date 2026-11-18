<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\TelegramUpdateMessage;
use App\MessageHandler\TelegramUpdateMessageHandler;
use App\Service\TelegramUpdateHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class TelegramUpdateMessageHandlerTest extends TestCase
{
    /**
     * Verifies a fresh update is marked as seen and handled once.
     */
    public function testFreshUpdateIsMarkedAndHandled(): void
    {
        $update = ['update_id' => 42, 'message' => ['text' => 'hello']];

        $stored = null;
        $item   = $this->cacheItem($stored);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_update_42')->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $cache))(new TelegramUpdateMessage($update));

        $this->assertTrue($stored);
    }

    /**
     * Verifies a redelivered update is dropped without any side effect.
     */
    public function testRedeliveredUpdateIsSkipped(): void
    {
        $update = ['update_id' => 42, 'message' => ['text' => 'hello']];

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('getItem')
            ->with('telegram_update_42')
            ->willReturn($this->cacheItem(isHit: true));
        $cache->expects($this->never())->method('save');

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        (new TelegramUpdateMessageHandler($handler, $cache))(new TelegramUpdateMessage($update));
    }

    /**
     * Verifies an update without an id is handled without touching the cache.
     */
    public function testUpdateWithoutIdIsHandledWithoutDeduplication(): void
    {
        $update = ['message' => ['text' => 'hello']];

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('getItem');
        $cache->expects($this->never())->method('save');

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $cache))(new TelegramUpdateMessage($update));
    }

    private function cacheItem(mixed &$stored = null, bool $isHit = false): CacheItemInterface
    {
        $item = $this->createStub(CacheItemInterface::class);

        $item->method('set')->willReturnCallback(static function (mixed $value) use (&$stored, $item): CacheItemInterface {
            $stored = $value;

            return $item;
        });
        $item->method('expiresAfter')->willReturnCallback(static fn (): CacheItemInterface => $item);
        $item->method('isHit')->willReturn($isHit);

        return $item;
    }
}
