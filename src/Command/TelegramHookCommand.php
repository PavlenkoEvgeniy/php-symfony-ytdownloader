<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'telegram:hook',
    description: 'Add a webhook for the Telegram bot',
)]
class TelegramHookCommand extends Command
{
    public function __construct(
        private readonly string $telegramBotToken,
        private readonly string $telegramHostUrl,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $route = sprintf('%s%s', $this->telegramHostUrl, '/telegram/hook');

        $url = sprintf('https://api.telegram.org/bot%s/setWebhook', $this->telegramBotToken);

        $context = \stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => \http_build_query([
                    'url' => $route,
                ]),
            ],
        ]);

        $result = \file_get_contents($url, false, $context);

        $io->info(sprintf('Reply from telegram: %s', $result));

        return Command::SUCCESS;
    }
}
