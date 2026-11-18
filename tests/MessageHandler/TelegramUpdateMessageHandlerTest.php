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
        $update = ['update_id' => 42, 'message' => ['message_id' => 7, 'chat' => ['id' => 42]]];

        $claims     = [];
        $statements = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$claims, &$statements): int {
                $statements[] = $sql;

                if (\str_contains($sql, 'INSERT')) {
                    $claims[] = $params['key'];

                    return 1;
                }

                return 0;
            },
        );

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));

        $this->assertSame(['message:42:7'], $claims);
        $this->assertCount(2, $statements);
        $this->assertStringContainsString('ON CONFLICT DO NOTHING', $statements[0]);
        $this->assertStringContainsString('DELETE', $statements[1]);
    }

    /**
     * Verifies the same message delivered again - even with a different
     * update id, as the Bot API server renumbers them - loses the claim.
     */
    public function testRedeliveredMessageIsSkippedRegardlessOfUpdateId(): void
    {
        $first = ['update_id' => 67911788, 'message' => ['message_id' => 901, 'chat' => ['id' => 5177124243]]];
        $again = ['update_id' => 239585547, 'message' => ['message_id' => 901, 'chat' => ['id' => 5177124243]]];

        $claims     = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$claims): int {
                if (!\str_contains($sql, 'INSERT')) {
                    return 0;
                }

                $claims[] = $params['key'];

                // First claim wins, the second is the conflict loser.
                return 1 === \count($claims) ? 1 : 0;
            },
        );

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle');

        $messageHandler = new TelegramUpdateMessageHandler($handler, $connection);
        $messageHandler(new TelegramUpdateMessage($first));
        $messageHandler(new TelegramUpdateMessage($again));

        $this->assertSame(['message:5177124243:901', 'message:5177124243:901'], $claims);
    }

    /**
     * Verifies a callback query is claimed by its own unique id.
     */
    public function testCallbackQueryClaimUsesCallbackId(): void
    {
        $update = ['update_id' => 5, 'callback_query' => ['id' => 'callback-1']];

        $claims     = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$claims): int {
                if (\str_contains($sql, 'INSERT')) {
                    $claims[] = $params['key'];
                }

                return 1;
            },
        );

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle');

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));

        $this->assertSame(['callback:callback-1'], $claims);
    }

    /**
     * Verifies a payload without any stable identity is handled without a
     * database claim.
     */
    public function testUpdateWithoutIdentityIsHandledWithoutClaim(): void
    {
        $update = ['foo' => 'bar'];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $handler = $this->createMock(TelegramUpdateHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->with($update);

        (new TelegramUpdateMessageHandler($handler, $connection))(new TelegramUpdateMessage($update));
    }
}
