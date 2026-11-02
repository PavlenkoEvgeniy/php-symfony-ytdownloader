<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DownloadDispatcherInterface;
use App\Service\TelegramMessageHandler;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Users\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TelegramMessageHandlerTest extends TestCase
{
    /**
     * Verifies the start command replies with the prompt.
     */
    public function testOnStartRepliesWithPrompt(): void
    {
        $bot = $this->createMock(BotMan::class);
        $bot->expects($this->once())
            ->method('reply')
            ->with('Please send me a link for download');

        $handler = new TelegramMessageHandler(
            $this->createStub(DownloadDispatcherInterface::class),
            $this->createTranslator('telegram.please_send_link', 'Please send me a link for download'),
        );

        $handler->onStart($bot);
    }

    /**
     * Verifies a URL message dispatches a download and replies to the user.
     */
    public function testOnUrlDispatchesDownloadAndReplies(): void
    {
        $bot = $this->createMock(BotMan::class);
        $bot->method('getUser')->willReturn(new User(42));
        $bot->expects($this->once())
            ->method('reply')
            ->with('Downloading is in progress. Please wait...');

        $dispatcher = $this->createMock(DownloadDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with('https://test.local', 'best', '42');

        $handler = new TelegramMessageHandler(
            $dispatcher,
            $this->createTranslator('telegram.downloading_in_progress', 'Downloading is in progress. Please wait...'),
        );

        $handler->onUrl($bot, 'https://test.local');
    }

    private function createTranslator(string $expectedKey, string $translation): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with($expectedKey)
            ->willReturn($translation);

        return $translator;
    }
}
