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

    /**
     * Verifies message edits post the message id and an empty keyboard by default.
     */
    public function testEditMessageTextPostsTheMessageId(): void
    {
        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"result":{}}');
        });

        new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->editMessageText(42, 7, 'halfway');

        $this->assertSame('https://api.telegram.org/bottoken/editMessageText', $captured['url']);

        $body = (string) $captured['options']['body'];
        $this->assertStringContainsString('"chat_id":"42"', $body);
        $this->assertStringContainsString('"message_id":7', $body);
        $this->assertStringContainsString('"text":"halfway"', $body);
        $this->assertStringContainsString('"inline_keyboard":[]', $body);
    }

    /**
     * Verifies a callback answer carries the optional confirmation text.
     */
    public function testAnswerCallbackQueryCarriesTheText(): void
    {
        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true,"result":true}');
        });

        new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->answerCallbackQuery('query-1', 'Queued');

        $this->assertSame('https://api.telegram.org/bottoken/answerCallbackQuery', $captured['url']);

        $body = (string) $captured['options']['body'];
        $this->assertStringContainsString('"callback_query_id":"query-1"', $body);
        $this->assertStringContainsString('"text":"Queued"', $body);
    }

    /**
     * Verifies an empty callback answer omits the text field.
     */
    public function testAnswerCallbackQueryOmitsEmptyText(): void
    {
        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['options' => $options];

            return new MockResponse('{"ok":true,"result":true}');
        });

        new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->answerCallbackQuery('query-1');

        $body = (string) $captured['options']['body'];
        $this->assertStringContainsString('"callback_query_id":"query-1"', $body);
        $this->assertStringNotContainsString('"text"', $body);
    }

    /**
     * Verifies audio files are uploaded to the sendAudio method.
     */
    public function testSendAudioStreamsFile(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'telegram_audio_');
        $this->assertIsString($file);
        \file_put_contents($file, 'audio');

        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['url' => $url];

            return new MockResponse('{"ok":true,"result":{"message_id":78}}');
        });

        try {
            $messageId = new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->sendAudio(42, $file, 'song');
        } finally {
            @\unlink($file);
        }

        $this->assertSame(78, $messageId);
        $this->assertSame('https://api.telegram.org/bottoken/sendAudio', $captured['url']);
    }

    /**
     * Verifies an unreadable file fails before any request is made.
     */
    public function testSendVideoFailsOnUnreadableFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not readable');

        new TelegramClient(new MockHttpClient(), 'token', 'https://api.telegram.org')->sendVideo(
            42,
            '/does/not/exist/video.mp4',
            'caption',
        );
    }

    /**
     * Verifies a transport failure is converted to a domain exception.
     */
    public function testTransportFailureBecomesATelegramApiException(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('network is down');
        });

        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Telegram API method "sendMessage" failed with HTTP 0: network is down');

        new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->sendMessage(42, 'hello');
    }

    /**
     * Verifies a non-JSON body without a description reports "invalid response".
     */
    public function testInvalidResponseBecomesATelegramApiException(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('not json'));

        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Telegram API method "sendMessage" failed with HTTP 200: invalid response');

        new TelegramClient($httpClient, 'token', 'https://api.telegram.org')->sendMessage(42, 'hello');
    }
}
