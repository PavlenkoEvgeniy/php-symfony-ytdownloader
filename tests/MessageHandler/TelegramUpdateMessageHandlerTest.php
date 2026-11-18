<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\TelegramUpdateMessage;
use App\MessageHandler\TelegramUpdateMessageHandler;
use App\Service\TelegramUpdateHandlerInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class TelegramUpdateMessageHandlerTest extends TestCase
{
    /**
     * Verifies a fresh update wins the atomic claim and is handled once.
     */
    public function testFreshUpdateIsClaimedAndHandled(): void
    {
        $update = ['update_id' => 42, 'message' => ['text' => 'hello']];

        $statements = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                return 'INSERT' === \strtok(\ltrim($sql), ' ') ? 1 : 0;
            },
        );

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('ON CONFLICT DO NOTHING', $statements[0]);
        $this->assertStringContainsString('DELETE', $statements[1]);
    }

    /**
     * Verifies a duplicate arrival loses the atomic claim and has no side effects.
     */
    public function testDuplicateUpdateIsSkipped(): void
    {
        $update = ['update_id' => 42, 'message' => ['text' => 'hello']];

        $statements = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                return 0;
            },
        );

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));

        $this->assertCount(1, $statements);
        $this->assertStringContainsString('ON CONFLICT DO NOTHING', $statements[0]);
    }

    /**
     * Verifies an update without an id is handled without touching the database.
     */
    public function testUpdateWithoutIdIsHandledWithoutClaim(): void
    {
        $update = ['message' => ['text' => 'hello']];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));
    }
}
