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
        #[Autowire(env: 'string:TELEGRAM_WEBHOOK_SECRET')]
        private string $telegramWebhookSecret,
        private HttpClientInterface $httpClient,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $route = \sprintf('%s%s', $this->telegramHostUrl, '/telegram/hook');

        $url = \sprintf('https://api.telegram.org/bot%s/setWebhook', $this->telegramBotToken);

        $body = [
            'url'                   => $route,
            'drop_pending_updates'  => 'true',
        ];

        if ('' !== $this->telegramWebhookSecret) {
            $body['secret_token'] = $this->telegramWebhookSecret;
        }

        $response = $this->httpClient->request('POST', $url, ['body' => $body]);

        $result = $response->getContent(false);

        $io->info($this->translator->trans('command.telegram.reply', ['%reply%' => $result]));

        return Command::SUCCESS;
    }
}
