<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TelegramUpdateMessage;
use App\Service\TelegramUpdateHandlerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes a Telegram update outside the webhook request. The webhook and
 * the poller may deliver the same update, possibly to two workers at once,
 * so the claim is a single atomic insert: only the first arrival wins and
 * the rest are dropped before any side effect. Repeating a reply storm is
 * worse than losing one accepted update.
 */
#[AsMessageHandler]
final readonly class TelegramUpdateMessageHandler
{
    private const string CLAIM_SQL = 'INSERT INTO telegram_update_dedupe (update_id) VALUES (:id) ON CONFLICT DO NOTHING';

    private const string PRUNE_SQL = "DELETE FROM telegram_update_dedupe WHERE seen_at < NOW() - INTERVAL '7 days'";

    public function __construct(
        private TelegramUpdateHandlerInterface $telegramUpdateHandler,
        private Connection $connection,
    ) {
    }

    public function __invoke(TelegramUpdateMessage $message): void
    {
        if (!$this->claim($message->update)) {
            return;
        }

        $this->telegramUpdateHandler->handle($message->update);
    }

    /**
     * @param array<string, mixed> $update
     *
     * @return bool False when another arrival already owns this update
     */
    private function claim(array $update): bool
    {
        $updateId = $update['update_id'] ?? null;

        if (!\is_int($updateId)) {
            // A payload without an id cannot be told apart from a fresh one.
            return true;
        }

        $inserted = (int) $this->connection->executeStatement(self::CLAIM_SQL, ['id' => $updateId]);

        if (0 === $inserted) {
            return false;
        }

        // Volume is a handful of updates per day, so pruning on the claim
        // path keeps the table tiny without a scheduled job.
        $this->connection->executeStatement(self::PRUNE_SQL);

        return true;
    }
}
