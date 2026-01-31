<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:telegram-hook',
    description: 'Add a webhook for the Telegram bot',
)]
final class TelegramHookCommand extends Command
{
    public function __construct(
        private readonly string $telegramBotToken,
        private readonly string $telegramHostUrl,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $route = \sprintf('%s%s', $this->telegramHostUrl, '/telegram/hook');

        $url = \sprintf('https://api.telegram.org/bot%s/setWebhook', $this->telegramBotToken);

        $response = $this->httpClient->request('POST', $url, [
            'body' => [
                'url' => $route,
            ],
        ]);

        $result = $response->getContent(false);

        $io->info(\sprintf('Reply from telegram: %s', $result));

        return Command::SUCCESS;
    }
}
