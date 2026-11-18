<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\TelegramUpdateMessage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Delivers Telegram updates by polling the Bot API server. The webhook and
 * this poller feed the same at-most-once processing, so both may run while
 * one of the paths is degraded; polling keeps the bot answering when cloud
 * webhook delivery stalls.
 */
#[AsCommand(name: 'app:telegram-poll', description: 'Delivers Telegram updates from the Bot API server by polling')]
final readonly class TelegramPollCommand
{
    private const string OFFSET_CACHE_KEY   = 'telegram_poll_offset';
    private const int POLL_TIMEOUT_SECONDS  = 20;
    private const int HTTP_TIMEOUT_MARGIN   = 10;
    private const int ERROR_BACKOFF_SECONDS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
        #[Autowire(env: 'string:TELEGRAM_API_BASE_URL')]
        private string $telegramApiBaseUrl,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Run a single poll cycle and exit')]
        bool $once = false,
    ): int {
        $offset = $this->initialOffset();

        do {
            $offset = $this->pollOnce($offset);
        } while (!$once);

        $io->success(\sprintf('Polled up to update %d.', \max(0, $offset - 1)));

        return Command::SUCCESS;
    }

    /**
     * Fetches one batch and queues every update before moving the offset past
     * it, so a crash re-delivers a batch that the update-id dedup absorbs.
     */
    private function pollOnce(int $offset): int
    {
        $updates = $this->fetchUpdates($offset);

        if (null === $updates) {
            \sleep(self::ERROR_BACKOFF_SECONDS);

            return $offset;
        }

        foreach ($updates as $update) {
            if (!\is_array($update)) {
                continue;
            }

            $this->messageBus->dispatch(new TelegramUpdateMessage($update));

            $offset = ((int) ($update['update_id'] ?? 0)) + 1;
            $this->saveOffset($offset);
        }

        return $offset;
    }

    /**
     * @return list<mixed>|null Null when the request failed and must be retried
     */
    private function fetchUpdates(int $offset): ?array
    {
        try {
            $result = $this->httpClient->request('GET', $this->apiUrl('getUpdates'), [
                'query'   => ['offset' => $offset, 'limit' => 100, 'timeout' => self::POLL_TIMEOUT_SECONDS],
                'timeout' => self::POLL_TIMEOUT_SECONDS + self::HTTP_TIMEOUT_MARGIN,
            ])->toArray(false);
        } catch (\Throwable $exception) {
            $this->logger->error('Telegram polling request failed', ['error' => $exception->getMessage()]);

            return null;
        }

        if (true !== ($result['ok'] ?? false)) {
            $this->logger->error('Telegram polling was rejected', ['description' => $result['description'] ?? 'unknown']);

            return null;
        }

        return \is_array($result['result'] ?? null) ? $result['result'] : [];
    }

    /**
     * A cold start begins after the newest existing update: a long outage
     * must not replay an old backlog of links and cancellations.
     */
    private function initialOffset(): int
    {
        $item = $this->cache->getItem(self::OFFSET_CACHE_KEY);

        if ($item->isHit()) {
            return (int) $item->get();
        }

        $latest = null;

        try {
            $result = $this->httpClient->request('GET', $this->apiUrl('getUpdates'), [
                'query'   => ['offset' => -1, 'limit' => 1],
                'timeout' => self::POLL_TIMEOUT_SECONDS,
            ])->toArray(false);

            $latest = $result['result'][0]['update_id'] ?? null;
        } catch (\Throwable $exception) {
            $this->logger->warning('Telegram polling could not read the newest update', ['error' => $exception->getMessage()]);
        }

        $offset = \is_numeric($latest) ? (int) $latest + 1 : 0;
        $this->saveOffset($offset);

        return $offset;
    }

    private function saveOffset(int $offset): void
    {
        $item = $this->cache->getItem(self::OFFSET_CACHE_KEY);
        $item->set($offset);
        $this->cache->save($item);
    }

    private function apiUrl(string $method): string
    {
        return \sprintf('%s/bot%s/%s', \rtrim($this->telegramApiBaseUrl, '/'), $this->telegramBotToken, $method);
    }
}
