<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TelegramUpdateMessage;
use App\Service\TelegramUpdateHandlerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes a Telegram update outside the webhook request. Telegram may
 * redeliver the same update when an acknowledgement is lost, so the update
 * id is marked in the cache before handling starts: repeating a reply storm
 * is worse than losing one accepted update.
 */
#[AsMessageHandler]
final readonly class TelegramUpdateMessageHandler
{
    private const int SEEN_UPDATE_TTL = 1800;

    public function __construct(
        private TelegramUpdateHandlerInterface $telegramUpdateHandler,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(TelegramUpdateMessage $message): void
    {
        if ($this->isRedelivery($message->update)) {
            return;
        }

        $this->telegramUpdateHandler->handle($message->update);
    }

    /**
     * @param array<string, mixed> $update
     */
    private function isRedelivery(array $update): bool
    {
        $updateId = $update['update_id'] ?? null;

        if (!\is_int($updateId)) {
            // A payload without an id cannot be told apart from a fresh one.
            return false;
        }

        $item = $this->cache->getItem('telegram_update_' . $updateId);

        if ($item->isHit()) {
            return true;
        }

        $item->set(true);
        $item->expiresAfter(self::SEEN_UPDATE_TTL);
        $this->cache->save($item);

        return false;
    }
}
