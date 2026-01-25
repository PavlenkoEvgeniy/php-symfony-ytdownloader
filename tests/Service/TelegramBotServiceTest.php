<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramBotService;
use BotMan\BotMan\BotMan;
use PHPUnit\Framework\TestCase;

final class TelegramBotServiceTest extends TestCase
{
    public function testGetBotReturnsBotManInstance(): void
    {
        $service = new TelegramBotService('dummy-token');

        $bot = $service->getBot();

        $this->assertInstanceOf(BotMan::class, $bot);
    }
}
