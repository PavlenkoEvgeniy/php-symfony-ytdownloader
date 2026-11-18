<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditChannel;
use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TelegramUpdateHandler implements TelegramUpdateHandlerInterface
{
    private const int LINK_TOKEN_TTL    = 600;
    private const int PENDING_TOKEN_TTL = 900;

    /** @var list<string> */
    private const array QUALITY_CODES = ['best', 'moderate', 'poor', 'audio'];

    /** @var list<string> */
    private const array LANGUAGE_CODES = ['en', 'ru', 'es', 'pt', 'de', 'fr', 'tr', 'ar', 'hi', 'zh'];

    /** @var array<string, string> */
    private const array LANGUAGE_NAMES = [
        'en' => 'English',
        'ru' => 'Русский',
        'es' => 'Español',
        'pt' => 'Português',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'tr' => 'Türkçe',
        'ar' => 'العربية',
        'hi' => 'हिन्दी',
        'zh' => '中文',
    ];

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private DownloadTaskRepositoryInterface $downloadTaskRepository,
        private DownloadDispatcherInterface $downloadDispatcher,
        private DownloadRetryServiceInterface $downloadRetryService,
        private TelegramClientInterface $telegramClient,
        private YoutubeDlProbeInterface $probe,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire(env: 'string:TELEGRAM_HOST_URL')]
        private string $telegramHostUrl,
        private FormatResolverInterface $formatResolver,
        private DownloadSizeEstimatorInterface $sizeEstimator,
        #[Autowire(env: 'int:TELEGRAM_MAX_UPLOAD_MB')]
        private int $telegramMaxUploadMb,
        private TranslatorInterface $translator,
        #[Autowire(env: 'string:APP_VERSION')]
        private string $appVersion,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function t(string $key, array $parameters = [], ?string $locale = null): string
    {
        return $this->translator->trans($key, $parameters, 'messages', $locale);
    }

    private function localeOf(?User $user): string
    {
        $locale = $user?->getBotLocale();

        return null === $locale ? 'en' : $locale;
    }

    #[\Override]
    public function handle(array $update): void
    {
        $callback = $update['callback_query'] ?? null;

        if (\is_array($callback)) {
            $this->handleCallback($callback);

            return;
        }

        $message = $update['message'] ?? null;

        if (!\is_array($message)) {
            return;
        }

        $chat     = $message['chat'] ?? [];
        $from     = $message['from'] ?? [];
        $chatId   = \is_array($chat) ? (int) ($chat['id'] ?? 0) : 0;
        $userId   = \is_array($from) ? (int) ($from['id'] ?? 0) : 0;
        $chatType = \is_array($chat) ? (string) ($chat['type'] ?? '') : '';
        $text     = \trim((string) ($message['text'] ?? ''));

        if (0 === $chatId || 0 === $userId) {
            return;
        }

        if ('private' !== $chatType) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.private_only'));

            return;
        }

        if (\str_starts_with($text, '/')) {
            $this->handleCommand($this->parseCommand($text), $chatId, $userId, $from);

            return;
        }

        if (1 === \preg_match('#https://\S+#i', $text, $matches)) {
            $this->handleUrl($matches[0], $chatId, $userId);

            return;
        }

        $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.send_link'), $this->hiddenKeyboard());
    }

    private function parseCommand(string $text): string
    {
        $command = \strtolower(\explode(' ', \trim($text), 2)[0]);

        return \explode('@', $command, 2)[0];
    }

    /**
     * @param array<string, mixed> $from
     */
    private function handleCommand(string $command, int $chatId, int $userId, array $from): void
    {
        match ($command) {
            '/start'    => $this->handleStart($chatId, $userId),
            '/login'    => $this->handleLogin($chatId, $userId, $from),
            '/logout'   => $this->handleLogout($chatId, $userId),
            '/cancel'   => $this->handleCancel($chatId, $userId),
            '/status'   => $this->handleStatus($chatId, $userId),
            '/info'     => $this->handleInfo($chatId, $userId),
            '/language' => $this->handleLanguage($chatId, $userId),
            default     => $this->sendHelp($chatId, $userId),
        };
    }

    private function handleStart(int $chatId, int $userId): void
    {
        $user = $this->userRepository->findOneByTelegramUserId($userId);

        if ($user instanceof User && $user->getIsEnabled()) {
            $this->telegramClient->sendMessage($chatId, $this->linkedWelcome($user), $this->hiddenKeyboard());

            return;
        }

        $this->telegramClient->sendMessage(
            $chatId,
            $this->t('telegram.bot.welcome_unlinked'),
            $this->hiddenKeyboard(),
        );
    }

    /**
     * @param array<string, mixed> $from
     */
    private function handleLogin(int $chatId, int $userId, array $from): void
    {
        $existing = $this->userRepository->findOneByTelegramUserId($userId);

        if ($existing instanceof User) {
            $this->telegramClient->sendMessage(
                $chatId,
                $this->t('telegram.bot.login_already_linked', ['%account%' => $this->telegramLabel($existing)], $this->localeOf($existing)),
                $this->hiddenKeyboard(),
            );

            return;
        }

        $token = \bin2hex(\random_bytes(16));
        $item  = $this->cache->getItem($this->linkTokenKey($token));
        $item->set([
            'telegram_user_id'  => $userId,
            'telegram_username' => \is_string($from['username'] ?? null) ? $from['username'] : null,
        ]);
        $item->expiresAfter(self::LINK_TOKEN_TTL);
        $this->cache->save($item);

        $url = \sprintf('%s/telegram/link?token=%s', \rtrim($this->telegramHostUrl, '/'), $token);

        $this->telegramClient->sendMessage(
            $chatId,
            $this->t('telegram.bot.login_flow', ['%url%' => $url]),
            $this->hiddenKeyboard(),
        );
    }

    private function handleLogout(int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $this->cache->deleteItem($this->pendingKey($chatId));
        $this->downloadTaskRepository->deleteQueuedByUser($user);

        $user->unlinkTelegram();
        $this->entityManager->flush();

        $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.logout_done', [], $this->localeOf($user)), $this->hiddenKeyboard());
    }

    private function handleCancel(int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $this->cache->deleteItem($this->pendingKey($chatId));

        // Active means queued or processing: a running transfer stops too,
        // which is what frees the user's single download slot.
        $cancelled = $this->downloadTaskRepository->deleteActiveByUser($user);

        $this->telegramClient->sendMessage(
            $chatId,
            $cancelled > 0
                ? $this->t('telegram.bot.cancelled', ['%count%' => $cancelled], $this->localeOf($user))
                : $this->t('telegram.bot.nothing_to_cancel', [], $this->localeOf($user)),
            $this->hiddenKeyboard(),
        );
    }

    private function handleStatus(int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $tasks = $this->downloadTaskRepository->findActiveByUser($user);

        if ([] === $tasks) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.status_empty', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return;
        }

        $lines = [];

        foreach ($tasks as $task) {
            $lines[] = \sprintf(
                '#%d %s · %s · %s',
                $task->getIdOrFail(),
                $task->getStatusOrFail(),
                $task->getQualityOrFail(),
                $task->getUrlOrFail(),
            );
        }

        $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.status_list', ['%list%' => \implode("\n", $lines)], $this->localeOf($user)), $this->hiddenKeyboard());
    }

    private function handleInfo(int $chatId, int $userId): void
    {
        $locale = $this->localeOf($this->userRepository->findOneByTelegramUserId($userId));

        $this->telegramClient->sendMessage(
            $chatId,
            $this->t('telegram.info.reply', [
                '%version%' => $this->appVersion,
                '%updated%' => $this->lastUpdateTime($locale),
            ], $locale),
            $this->hiddenKeyboard(),
        );
    }

    /**
     * Offers the language list; the choice is saved per linked user.
     */
    private function handleLanguage(int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $locale = $this->localeOf($user);

        $this->telegramClient->sendMessage($chatId, $this->t('telegram.language.prompt', [], $locale), [
            'inline_keyboard' => \array_chunk(
                \array_map(
                    static fn (string $code): array => [
                        'text'          => self::LANGUAGE_NAMES[$code],
                        'callback_data' => 'lang:' . $code,
                    ],
                    self::LANGUAGE_CODES,
                ),
                2,
            ),
        ]);
    }

    /**
     * The release checkout rewrites .env at every deploy (the version bump),
     * so its mtime marks when this version arrived.
     */
    private function lastUpdateTime(string $locale): string
    {
        $envFile   = \rtrim($this->projectDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        $timestamp = \is_file($envFile) ? @\filemtime($envFile) : false;

        return false === $timestamp
            ? $this->t('telegram.info.unknown', [], $locale)
            : \gmdate('Y-m-d H:i', $timestamp);
    }

    private function sendHelp(int $chatId, int $userId): void
    {
        $locale = $this->localeOf($this->userRepository->findOneByTelegramUserId($userId));

        $this->telegramClient->sendMessage(
            $chatId,
            $this->t('telegram.bot.help', [], $locale),
            $this->hiddenKeyboard(),
        );
    }

    private function handleUrl(string $url, int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        if (\strlen($url) > 512) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.link_too_long', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return;
        }

        if ($this->downloadTaskRepository->findActiveByUserAndUrl($user, $url) instanceof DownloadTask) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.link_already_queued', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return;
        }

        // One download at a time per user: an active task answers before the
        // link is even probed.
        if ([] !== $this->downloadTaskRepository->findActiveByUser($user, 1)) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.active_download_hint', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return;
        }

        $locale = $this->localeOf($user);

        $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.checking_link', [], $locale));

        try {
            $probe = $this->probe->probe($url);
        } catch (\Throwable $exception) {
            $this->logger->info('Telegram link probe failed', ['url' => $url, 'error' => $exception->getMessage()]);
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.probe_failed', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return;
        }

        if ($probe->isPlaylist) {
            $this->telegramClient->sendMessage(
                $chatId,
                $this->t('telegram.bot.playlist_unsupported', [], $this->localeOf($user)),
                $this->hiddenKeyboard(),
            );

            return;
        }

        $token = \bin2hex(\random_bytes(16));
        $item  = $this->cache->getItem($this->pendingKey($chatId));
        $item->set([
            'token'            => $token,
            'url'              => $url,
            'title'            => $probe->title,
            'telegram_user_id' => $userId,
        ]);
        $item->expiresAfter(self::PENDING_TOKEN_TTL);
        $this->cache->save($item);

        $buttons = [];

        foreach (self::QUALITY_CODES as $quality) {
            $buttons[] = [
                'text'          => $this->t('telegram.bot.quality.' . $quality, [], $locale),
                'callback_data' => \sprintf('dl:%s:%s', $token, $quality),
            ];
        }

        $caption = $probe->title;

        if (null !== $probe->duration) {
            $caption .= \sprintf(' (%s)', $this->formatDuration($probe->duration));
        }

        $this->telegramClient->sendMessage($chatId, $caption . "\n\n" . $this->t('telegram.bot.choose_quality', [], $locale), [
            'inline_keyboard' => \array_chunk($buttons, 2),
        ]);
    }

    #[\Override]
    public function offerQualitySelection(
        int $chatId,
        int $telegramUserId,
        string $url,
        string $title,
        string $rejectedQuality,
    ): void {
        $locale  = $this->localeOf($this->userRepository->findOneByTelegramUserId($telegramUserId));
        $offered = $this->offeredQualities($url, $rejectedQuality);

        if ([] === $offered) {
            try {
                $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.no_quality_fits', [], $locale), $this->hiddenKeyboard());
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to send the empty quality selection answer', ['error' => $exception->getMessage()]);
            }

            return;
        }

        // The selection replaces any pending one: a late rejection outranks an
        // older keyboard, whose token then simply reports "expired".
        $token = \bin2hex(\random_bytes(16));
        $item  = $this->cache->getItem($this->pendingKey($chatId));
        $item->set([
            'token'            => $token,
            'url'              => $url,
            'title'            => $title,
            'telegram_user_id' => $telegramUserId,
        ]);
        $item->expiresAfter(self::PENDING_TOKEN_TTL);
        $this->cache->save($item);

        $buttons = [];

        foreach (self::QUALITY_CODES as $quality) {
            if (\in_array($quality, $offered, true)) {
                $buttons[] = [
                    'text'          => $this->t('telegram.bot.quality.' . $quality, [], $locale),
                    'callback_data' => \sprintf('dl:%s:%s', $token, $quality),
                ];
            }
        }

        try {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.choose_quality', [], $locale), [
                'inline_keyboard' => \array_chunk($buttons, 2),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send the quality selection', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * The qualities are ordered from the largest file to the smallest, so a
     * cut decision — the rejected quality or one estimated above the upload
     * limit — takes every same-or-larger quality with it. Only qualities
     * after the rejected one are worth probing: a larger quality is cut
     * regardless of its own estimate. An unknown estimate keeps a smaller
     * quality (the transfer guard stays the hard limit).
     *
     * @return list<string>
     */
    private function offeredQualities(string $url, string $rejectedQuality): array
    {
        $names          = self::QUALITY_CODES;
        $rejectedIndex  = \array_search($rejectedQuality, $names, true);
        $rejectedIndex  = false === $rejectedIndex ? -1 : $rejectedIndex;
        $limitBytes     = $this->telegramMaxUploadMb * 1024 * 1024;
        $cutOffIndex    = $rejectedIndex;

        foreach ($names as $index => $quality) {
            if ($index <= $cutOffIndex) {
                continue;
            }

            [$downloadFormat] = $this->formatResolver->resolve($quality);
            $sizes            = $this->sizeEstimator->estimate($url, $downloadFormat, false);

            if ([] !== $sizes && $limitBytes < \max($sizes)) {
                $cutOffIndex = $index;
            }
        }

        $offered = [];

        foreach ($names as $index => $quality) {
            if ($index > $cutOffIndex) {
                $offered[] = $quality;
            }
        }

        return $offered;
    }

    /**
     * @param array<string, mixed> $callback
     */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data       = (string) ($callback['data'] ?? '');
        $from       = $callback['from'] ?? [];
        $message    = $callback['message'] ?? [];
        $chatId     = \is_array($message) ? (int) ($message['chat']['id'] ?? 0) : 0;
        $messageId  = \is_array($message) ? (int) ($message['message_id'] ?? 0) : 0;
        $userId     = \is_array($from) ? (int) ($from['id'] ?? 0) : 0;

        if (0 === $chatId || 0 === $userId || '' === $callbackId) {
            return;
        }

        if (1 === \preg_match('/^lang:([a-z]{2})$/', $data, $languageMatches)
            && \in_array($languageMatches[1], self::LANGUAGE_CODES, true)
        ) {
            $this->handleLanguageChoice($languageMatches[1], $callbackId, $chatId, $messageId, $userId);

            return;
        }

        if (1 === \preg_match('/^retry:(\d+)$/', $data, $retryMatches)) {
            $this->handleRetry((int) $retryMatches[1], $callbackId, $chatId, $userId);

            return;
        }

        if (1 !== \preg_match('/^dl:([a-f0-9]{32}):(best|moderate|poor|audio)$/', $data, $matches)) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.unknown_action'));

            return;
        }

        $item = $this->cache->getItem($this->pendingKey($chatId));

        /** @var array<string, mixed>|null $pending */
        $pending = $item->isHit() ? $item->get() : null;

        if (!\is_array($pending)
            || ($pending['token'] ?? null) !== $matches[1]
            || (int) ($pending['telegram_user_id'] ?? 0) !== $userId
        ) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.selection_expired'));

            return;
        }

        $quality = $matches[2];
        $user    = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.link_account_first'));

            return;
        }

        $this->cache->deleteItem($this->pendingKey($chatId));

        $url   = (string) ($pending['url'] ?? '');
        $title = (string) ($pending['title'] ?? 'Video');

        try {
            $this->downloadDispatcher->dispatch(
                $url,
                $quality,
                $user,
                DownloadTask::ORIGIN_TELEGRAM,
                $chatId,
                $title,
            );
        } catch (InsufficientDiskSpaceException) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.disk_space', [], $this->localeOf($user)));

            return;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to queue a Telegram download', ['url' => $url, 'error' => $exception->getMessage()]);
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.queue_failed', [], $this->localeOf($user)));

            return;
        }

        $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.queued_toast', [], $this->localeOf($user)));

        try {
            $this->telegramClient->editMessageText(
                $chatId,
                $messageId,
                $this->t('telegram.bot.queued_edit', ['%title%' => $title, '%quality%' => $this->t('telegram.bot.quality.' . $quality, [], $this->localeOf($user))], $this->localeOf($user)),
            );
        } catch (\Throwable $exception) {
            $this->logger->info('Failed to edit the Telegram quality message', ['error' => $exception->getMessage()]);
        }
    }

    private function handleRetry(int $taskId, string $callbackId, int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $task = $this->downloadTaskRepository->findById($taskId);

        if (!$task instanceof DownloadTask
            || $task->getUser()?->getId() !== $user->getId()
            || DownloadTask::STATUS_ERROR !== $task->getStatus()
        ) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.retry_impossible', [], $this->localeOf($user)));

            return;
        }

        // Re-download would queue a second task: the single-slot rule applies.
        if ([] !== $this->downloadTaskRepository->findActiveByUser($user, 1)) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.active_download_hint', [], $this->localeOf($user)));

            return;
        }

        $retried = $this->downloadRetryService->reDownload($taskId, $user, AuditChannel::TELEGRAM);

        $locale = $this->localeOf($user);

        $this->telegramClient->answerCallbackQuery(
            $callbackId,
            $retried
                ? $this->t('telegram.bot.retried', [], $locale)
                : $this->t('telegram.bot.retry_impossible', [], $locale),
        );
    }

    private function handleLanguageChoice(string $locale, string $callbackId, int $chatId, int $messageId, int $userId): void
    {
        $user = $this->userRepository->findOneByTelegramUserId($userId);

        if (!$user instanceof User) {
            $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.bot.link_account_first'));

            return;
        }

        $user->setBotLocale($locale);
        $this->entityManager->flush();

        $this->telegramClient->answerCallbackQuery($callbackId, $this->t('telegram.language.saved', [], $locale));

        if ($messageId > 0) {
            try {
                $this->telegramClient->editMessageText($chatId, $messageId, $this->t('telegram.language.saved', [], $locale), ['inline_keyboard' => []]);
            } catch (\Throwable $exception) {
                $this->logger->info('Failed to edit the language message', ['error' => $exception->getMessage()]);
            }
        }
    }

    private function resolveUser(int $chatId, int $userId): ?User
    {
        $user = $this->userRepository->findOneByTelegramUserId($userId);

        if (!$user instanceof User) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.not_linked'), $this->hiddenKeyboard());

            return null;
        }

        if (!$user->getIsEnabled()) {
            $this->telegramClient->sendMessage($chatId, $this->t('telegram.bot.account_disabled', [], $this->localeOf($user)), $this->hiddenKeyboard());

            return null;
        }

        return $user;
    }

    private function linkedWelcome(User $user): string
    {
        return $this->t('telegram.bot.linked_welcome', ['%account%' => $this->telegramLabel($user)], $this->localeOf($user));
    }

    private function telegramLabel(User $user): string
    {
        $username = $user->getTelegramUsername();

        return null === $username || '' === $username
            ? \sprintf('Telegram user %d', $user->getTelegramUserIdOrFail())
            : '@' . $username;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds >= 3600) {
            return \sprintf('%d:%02d:%02d', \intdiv($seconds, 3600), \intdiv($seconds % 3600, 60), $seconds % 60);
        }

        return \sprintf('%d:%02d', \intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Removes the reply keyboard so it never stays expanded over the chat.
     *
     * @return array<string, mixed>
     */
    private function hiddenKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    private function linkTokenKey(string $token): string
    {
        return 'telegram_link_' . $token;
    }

    private function pendingKey(int $chatId): string
    {
        return 'telegram_pending_' . $chatId;
    }
}
