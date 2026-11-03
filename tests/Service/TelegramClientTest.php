<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\TelegramApiException;
use App\Service\TelegramClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TelegramClientTest extends TestCase
{
    /**
     * Verifies text messages are sent to the configured API base URL.
     */
    public function testSendMessagePostsJson(): void
    {
        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"result":{}}');
        });

        new TelegramClient($httpClient, 'test-token', 'http://telegram-bot-api:8081')
            ->sendMessage(42, 'hello', ['inline_keyboard' => []]);

        $this->assertSame('POST', $captured['method']);
        $this->assertSame('http://telegram-bot-api:8081/bottest-token/sendMessage', $captured['url']);
        $this->assertStringContainsString('"chat_id":"42"', $captured['options']['body']);
        $this->assertStringContainsString('"text":"hello"', $captured['options']['body']);
    }

    /**
     * Verifies files are uploaded as multipart and the message id is returned.
     */
    public function testSendVideoStreamsFile(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'telegram_video_');
        $this->assertIsString($file);
        \file_put_contents($file, 'video');

        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"result":{"message_id":77}}');
        });

        try {
            $messageId = new TelegramClient($httpClient, 'token', 'https://api.telegram.org')
                ->sendVideo(42, $file, 'caption');
        } finally {
            @\unlink($file);
        }

        $this->assertSame(77, $messageId);
        $this->assertSame('https://api.telegram.org/bottoken/sendVideo', $captured['url']);
        $this->assertStringContainsString(
            'multipart/form-data',
            \implode("\n", $captured['options']['normalized_headers']['content-type'] ?? []),
        );
        $this->assertIsCallable($captured['options']['body']);
    }

    /**
     * Verifies Telegram errors are converted to a domain exception.
     */
    public function testSendFileThrowsOnApiError(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'telegram_video_');
        $this->assertIsString($file);

        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            '{"ok":false,"description":"Bad Request: chat not found"}',
            ['http_code' => 400],
        ));

        try {
            $this->expectException(TelegramApiException::class);

            new TelegramClient($httpClient, 'token', 'https://api.telegram.org')
                ->sendDocument(42, $file, 'caption');
        } finally {
            @\unlink($file);
        }
    }
}
