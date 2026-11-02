<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    description: 'Add a webhook for the Telegram bot',
    name: 'app:telegram-hook',
)]
final readonly class TelegramHookCommand
{
    public function __construct(
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
        #[Autowire(env: 'string:TELEGRAM_HOST_URL')]
        private string $telegramHostUrl,
        private HttpClientInterface $httpClient,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $route = \sprintf('%s%s', $this->telegramHostUrl, '/telegram/hook');

        $url = \sprintf('https://api.telegram.org/bot%s/setWebhook', $this->telegramBotToken);

        $response = $this->httpClient->request('POST', $url, [
            'body' => [
                'url' => $route,
            ],
        ]);

        $result = $response->getContent(false);

        $io->info($this->translator->trans('command.telegram.reply', ['%reply%' => $result]));

        return Command::SUCCESS;
    }
}
