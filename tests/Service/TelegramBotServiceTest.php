<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\TelegramBotService;
use BotMan\Drivers\Telegram\TelegramDriver;
use PHPUnit\Framework\TestCase;

final class TelegramBotServiceTest extends TestCase
{
    /**
     * Verifies the service exposes a BotMan instance using the Telegram driver.
     */
    public function testGetBotReturnsBotManInstance(): void
    {
        $service = new TelegramBotService('dummy-token');

        $bot = $service->getBot();

        $this->assertInstanceOf(TelegramDriver::class, $bot->getDriver());
    }
}
