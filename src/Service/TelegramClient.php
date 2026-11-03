<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TelegramApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TelegramClient implements TelegramClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
        #[Autowire(env: 'string:TELEGRAM_API_BASE_URL')]
        private string $telegramApiBaseUrl,
    ) {
    }

    #[\Override]
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => (string) $chatId,
            'text'    => $text,
        ];

        if (null !== $replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->request('sendMessage', $payload);
    }

    #[\Override]
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
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
