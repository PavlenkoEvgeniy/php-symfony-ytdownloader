<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    description: 'Register the bot command menu with Telegram',
    name: 'app:telegram-commands',
)]
final readonly class TelegramCommandsCommand
{
    /**
     * Commands shown in the Telegram menu next to the input field.
     */
    private const COMMANDS = [
        ['command' => 'start', 'description' => 'Start the bot'],
        ['command' => 'login', 'description' => 'Link your account'],
        ['command' => 'logout', 'description' => 'Unlink your account'],
        ['command' => 'cancel', 'description' => 'Cancel queued downloads'],
        ['command' => 'status', 'description' => 'List active downloads'],
        ['command' => 'help', 'description' => 'Show the command list'],
    ];

    public function __construct(
        #[Autowire(env: 'bool:TELEGRAM_BOT_ENABLED')]
        private bool $telegramBotEnabled,
        #[Autowire(env: 'string:TELEGRAM_BOT_TOKEN')]
        private string $telegramBotToken,
        #[Autowire(env: 'string:TELEGRAM_API_BASE_URL')]
        private string $telegramApiBaseUrl,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!$this->telegramBotEnabled) {
            $io->info('Telegram bot is disabled; the command menu was not registered.');

            return Command::SUCCESS;
        }

        $url = \sprintf(
            '%s/bot%s/setMyCommands',
            \rtrim($this->telegramApiBaseUrl, '/'),
            $this->telegramBotToken,
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json'    => ['commands' => self::COMMANDS],
                'timeout' => 20,
            ]);
            $contents = $response->getContent(false);
        } catch (\Throwable $exception) {
            $io->error(\sprintf('Could not register the command menu: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $decoded = \json_decode($contents, true);

        if (!\is_array($decoded) || true !== ($decoded['ok'] ?? false)) {
            $io->error(\sprintf('Telegram rejected the command menu: %s', $contents));

            return Command::FAILURE;
        }

        $io->success('The bot command menu is registered.');

        return Command::SUCCESS;
    }
}
