<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\TelegramPollCommand;
use App\Message\TelegramUpdateMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TelegramPollCommandTest extends TestCase
{
    /**
     * Verifies a cold start begins after the newest update and dispatches
     * the following batch through the messenger bus.
     */
    public function testColdStartPrimesAfterNewestUpdateAndDispatchesBatch(): void
    {
        $requests = [];

        $responses = [
            new MockResponse('{"ok":true,"result":[{"update_id":100}]}'),
            new MockResponse('{"ok":true,"result":[{"update_id":101,"message":{"text":"a"}},{"update_id":102,"message":{"text":"b"}}]}'),
        ];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = ['url' => $url, 'query' => $options['query'] ?? []];

            return \array_shift($responses) ?? new MockResponse('{"ok":true,"result":[]}');
        });

        $dispatched = [];
        $bus        = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $cache = new ArrayAdapter();

        $tester = new CommandTester(new TelegramPollCommand(
            $httpClient,
            $bus,
            $cache,
            $this->createStub(LoggerInterface::class),
            'test-token',
            'http://telegram-bot-api:8081',
        ));

        $tester->execute(['--once' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $this->assertCount(2, $requests);
        $this->assertSame(-1, $requests[0]['query']['offset']);
        $this->assertSame(101, $requests[1]['query']['offset']);
        $this->assertStringStartsWith('http://telegram-bot-api:8081/bottest-token/getUpdates', $requests[1]['url']);

        $this->assertCount(2, $dispatched);
        $this->assertInstanceOf(TelegramUpdateMessage::class, $dispatched[0]);
        $this->assertSame(101, $dispatched[0]->update['update_id']);
        $this->assertSame(102, $dispatched[1]->update['update_id']);

        $this->assertSame(103, $cache->getItem('telegram_poll_offset')->get());
    }

    /**
     * Verifies a stored offset resumes polling without peeking at the newest
     * update, and an empty batch dispatches nothing.
     */
    public function testStoredOffsetResumesWithoutPriming(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('telegram_poll_offset');
        $item->set(50);
        $cache->save($item);

        $requests = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['query'] ?? [];

            return new MockResponse('{"ok":true,"result":[]}');
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $tester = new CommandTester(new TelegramPollCommand(
            $httpClient,
            $bus,
            $cache,
            $this->createStub(LoggerInterface::class),
            'test-token',
            'http://telegram-bot-api:8081',
        ));

        $tester->execute(['--once' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertCount(1, $requests);
        $this->assertSame(50, $requests[0]['offset']);
        $this->assertSame(50, $cache->getItem('telegram_poll_offset')->get());
    }
}
