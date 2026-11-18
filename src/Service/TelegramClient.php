<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TelegramApiException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TelegramClient implements TelegramClientInterface
{
    /** Hard per-chat output ceiling: text, media and edits share one window. */
    private const int FLOOD_WINDOW_SECONDS  = 60;
    private const int FLOOD_MAX_MESSAGES    = 10;
    private const string FLOOD_CACHE_PREFIX = 'telegram_flood_';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
        #[Autowire(env: 'string:TELEGRAM_API_BASE_URL')]
        private string $telegramApiBaseUrl,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): int
    {
        if (!$this->allowSend($chatId)) {
            return 0;
        }

        $payload = [
            'chat_id' => (string) $chatId,
            'text'    => $text,
        ];

        if (null !== $replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $result = $this->request('sendMessage', $payload);

        return (int) ($result['message_id'] ?? 0);
    }

    #[\Override]
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        if (!$this->allowSend($chatId)) {
            return;
        }

        $this->request('editMessageText', [
            'chat_id'      => (string) $chatId,
            'message_id'   => $messageId,
            'text'         => $text,
            'reply_markup' => $replyMarkup ?? ['inline_keyboard' => []],
        ]);
    }

    #[\Override]
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ('' !== $text) {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    #[\Override]
    public function sendVideo(int $chatId, string $filePath, string $caption): int
    {
        return $this->sendFile('sendVideo', 'video', $chatId, $filePath, $caption, ['supports_streaming' => 'true']);
    }

    #[\Override]
    public function sendAudio(int $chatId, string $filePath, string $caption): int
    {
        return $this->sendFile('sendAudio', 'audio', $chatId, $filePath, $caption);
    }

    #[\Override]
    public function sendDocument(int $chatId, string $filePath, string $caption): int
    {
        return $this->sendFile('sendDocument', 'document', $chatId, $filePath, $caption);
    }

    /**
     * @param array<string, string> $extraFields
     */
    private function sendFile(
        string $method,
        string $field,
        int $chatId,
        string $filePath,
        string $caption,
        array $extraFields = [],
    ): int {
        if (!$this->allowSend($chatId)) {
            return 0;
        }

        $handle = \is_file($filePath) ? \fopen($filePath, 'rb') : false;

        if (false === $handle) {
            throw new \RuntimeException(\sprintf('File "%s" is not readable.', $filePath));
        }

        try {
            $result = $this->request($method, [
                'chat_id' => (string) $chatId,
                'caption' => \mb_substr($caption, 0, 1000),
                $field    => $handle,
            ] + $extraFields, multipart: true);
        } finally {
            \fclose($handle);
        }

        return (int) ($result['message_id'] ?? 0);
    }

    /**
     * The hard per-chat ceiling: however a bug or a delivery loop misbehaves,
     * a chat never receives more than the configured messages in one window.
     * The excess is dropped and logged instead of surfaced.
     */
    private function allowSend(int $chatId): bool
    {
        $item  = $this->cache->getItem(self::FLOOD_CACHE_PREFIX . $chatId);
        $state = $item->isHit() ? $item->get() : null;
        $now   = \time();

        if (!\is_array($state) || ($now - (int) $state['window']) >= self::FLOOD_WINDOW_SECONDS) {
            $state = ['window' => $now, 'count' => 0];
        }

        if ((int) $state['count'] >= self::FLOOD_MAX_MESSAGES) {
            $this->logger->error('Telegram flood guard dropped a message', [
                'chat_id'        => $chatId,
                'window_seconds' => self::FLOOD_WINDOW_SECONDS,
                'ceiling'        => self::FLOOD_MAX_MESSAGES,
            ]);

            return false;
        }

        ++$state['count'];

        $item->set($state);
        $item->expiresAfter(self::FLOOD_WINDOW_SECONDS * 2);
        $this->cache->save($item);

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload, bool $multipart = false): array
    {
        $url     = \sprintf('%s/bot%s/%s', \rtrim($this->telegramApiBaseUrl, '/'), $this->telegramBotToken, $method);
        $options = $multipart
            ? ['headers' => ['Content-Type' => 'multipart/form-data'], 'body' => $payload, 'timeout' => 600, 'max_duration' => 600]
            : ['json' => $payload, 'timeout' => 20, 'max_duration' => 30];

        try {
            $response = $this->httpClient->request('POST', $url, $options);
            $status   = $response->getStatusCode();
            $contents = $response->getContent(false);
        } catch (\Throwable $exception) {
            throw TelegramApiException::because($method, 0, $exception->getMessage());
        }

        $decoded = \json_decode($contents, true);

        if (!\is_array($decoded) || true !== ($decoded['ok'] ?? false)) {
            $description = \is_array($decoded) && \is_string($decoded['description'] ?? null)
                ? $decoded['description']
                : 'invalid response';

            throw TelegramApiException::because($method, $status, $description);
        }

        return \is_array($decoded['result'] ?? null) ? $decoded['result'] : [];
    }
}
