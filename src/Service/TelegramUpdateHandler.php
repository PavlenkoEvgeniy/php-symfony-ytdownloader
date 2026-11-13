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

final readonly class TelegramUpdateHandler implements TelegramUpdateHandlerInterface
{
    private const int LINK_TOKEN_TTL    = 600;
    private const int PENDING_TOKEN_TTL = 900;
    private const int MAX_ACTIVE_TASKS  = 3;

    /** @var list<array{string, string}> */
    private const array QUALITIES = [
        ['best', 'Best'],
        ['moderate', 'Moderate'],
        ['poor', 'Poor'],
        ['audio', 'Audio'],
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
    ) {
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
            $this->telegramClient->sendMessage($chatId, 'I work only in private chats.');

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

        $this->telegramClient->sendMessage($chatId, 'Send me an https:// link to a video.', $this->hiddenKeyboard());
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
            '/start'  => $this->handleStart($chatId, $userId),
            '/login'  => $this->handleLogin($chatId, $userId, $from),
            '/logout' => $this->handleLogout($chatId, $userId),
            '/cancel' => $this->handleCancel($chatId, $userId),
            '/status' => $this->handleStatus($chatId, $userId),
            default   => $this->sendHelp($chatId),
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
            "Welcome to YT Downloader.\n\nLink your account first with /login.",
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
                \sprintf('Already linked as %s. Use /logout to unlink.', $this->telegramLabel($existing)),
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
            \sprintf("Open this link to sign in and link your account:\n%s\n\nThe link expires in 10 minutes.", $url),
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

        $this->telegramClient->sendMessage($chatId, 'Unlinked. Queued downloads were cancelled.', $this->hiddenKeyboard());
    }

    private function handleCancel(int $chatId, int $userId): void
    {
        $user = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            return;
        }

        $this->cache->deleteItem($this->pendingKey($chatId));
        $cancelled = $this->downloadTaskRepository->deleteQueuedByUser($user);

        $this->telegramClient->sendMessage(
            $chatId,
            $cancelled > 0 ? \sprintf('Cancelled %d queued download(s).', $cancelled) : 'Nothing to cancel.',
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
            $this->telegramClient->sendMessage($chatId, 'No active downloads.', $this->hiddenKeyboard());

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

        $this->telegramClient->sendMessage($chatId, "Active downloads:\n" . \implode("\n", $lines), $this->hiddenKeyboard());
    }

    private function sendHelp(int $chatId): void
    {
        $this->telegramClient->sendMessage(
            $chatId,
            "Commands:\n/start — start\n/login — link your account\n/logout — unlink\n/cancel — cancel queued downloads\n/status — active downloads\n/help — this message\n\nOr send an https:// video link.",
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
            $this->telegramClient->sendMessage($chatId, 'The link is too long.', $this->hiddenKeyboard());

            return;
        }

        if ($this->downloadTaskRepository->findActiveByUserAndUrl($user, $url) instanceof DownloadTask) {
            $this->telegramClient->sendMessage($chatId, 'This link is already queued.', $this->hiddenKeyboard());

            return;
        }

        if ($this->downloadTaskRepository->countActiveByUser($user) >= self::MAX_ACTIVE_TASKS) {
            $this->telegramClient->sendMessage(
                $chatId,
                \sprintf('You already have %d downloads in progress.', self::MAX_ACTIVE_TASKS),
                $this->hiddenKeyboard(),
            );

            return;
        }

        $this->telegramClient->sendMessage($chatId, 'Checking link…');

        try {
            $probe = $this->probe->probe($url);
        } catch (\Throwable $exception) {
            $this->logger->info('Telegram link probe failed', ['url' => $url, 'error' => $exception->getMessage()]);
            $this->telegramClient->sendMessage($chatId, 'Could not read this link.', $this->hiddenKeyboard());

            return;
        }

        if ($probe->isPlaylist) {
            $this->telegramClient->sendMessage(
                $chatId,
                'Playlists are not supported. Send a link to a single video.',
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

        foreach (self::QUALITIES as [$quality, $label]) {
            $buttons[] = ['text' => $label, 'callback_data' => \sprintf('dl:%s:%s', $token, $quality)];
        }

        $caption = $probe->title;

        if (null !== $probe->duration) {
            $caption .= \sprintf(' (%s)', $this->formatDuration($probe->duration));
        }

        $this->telegramClient->sendMessage($chatId, $caption . "\n\nChoose quality:", [
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
        $offered = $this->offeredQualities($url, $rejectedQuality);

        if ([] === $offered) {
            try {
                $this->telegramClient->sendMessage($chatId, 'No other quality fits the Telegram limit.', $this->hiddenKeyboard());
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

        foreach (self::QUALITIES as [$quality, $label]) {
            if (\in_array($quality, $offered, true)) {
                $buttons[] = ['text' => $label, 'callback_data' => \sprintf('dl:%s:%s', $token, $quality)];
            }
        }

        try {
            $this->telegramClient->sendMessage($chatId, 'Choose quality:', [
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
        $names          = \array_column(self::QUALITIES, 0);
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

        if (1 === \preg_match('/^retry:(\d+)$/', $data, $retryMatches)) {
            $this->handleRetry((int) $retryMatches[1], $callbackId, $chatId, $userId);

            return;
        }

        if (1 !== \preg_match('/^dl:([a-f0-9]{32}):(best|moderate|poor|audio)$/', $data, $matches)) {
            $this->telegramClient->answerCallbackQuery($callbackId, 'Unknown action.');

            return;
        }

        $item = $this->cache->getItem($this->pendingKey($chatId));

        /** @var array<string, mixed>|null $pending */
        $pending = $item->isHit() ? $item->get() : null;

        if (!\is_array($pending)
            || ($pending['token'] ?? null) !== $matches[1]
            || (int) ($pending['telegram_user_id'] ?? 0) !== $userId
        ) {
            $this->telegramClient->answerCallbackQuery($callbackId, 'This selection has expired. Send the link again.');

            return;
        }

        $quality = $matches[2];
        $user    = $this->resolveUser($chatId, $userId);

        if (!$user instanceof User) {
            $this->telegramClient->answerCallbackQuery($callbackId, 'Please link your account first.');

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
            $this->telegramClient->answerCallbackQuery($callbackId, 'Not enough free disk space. Try again later.');

            return;
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to queue a Telegram download', ['url' => $url, 'error' => $exception->getMessage()]);
            $this->telegramClient->answerCallbackQuery($callbackId, 'Could not queue the download.');

            return;
        }

        $this->telegramClient->answerCallbackQuery($callbackId, 'Queued');

        try {
            $this->telegramClient->editMessageText(
                $chatId,
                $messageId,
                \sprintf('Queued: %s (%s)', $title, $quality),
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
            $this->telegramClient->answerCallbackQuery($callbackId, 'This download cannot be retried.');

            return;
        }

        $retried = $this->downloadRetryService->reDownload($taskId, $user, AuditChannel::TELEGRAM);

        $this->telegramClient->answerCallbackQuery(
            $callbackId,
            $retried ? 'Queued again.' : 'This download cannot be retried.',
        );
    }

    private function resolveUser(int $chatId, int $userId): ?User
    {
        $user = $this->userRepository->findOneByTelegramUserId($userId);

        if (!$user instanceof User) {
            $this->telegramClient->sendMessage($chatId, 'Your Telegram account is not linked. Use /login to link it.', $this->hiddenKeyboard());

            return null;
        }

        if (!$user->getIsEnabled()) {
            $this->telegramClient->sendMessage($chatId, 'This account is disabled.', $this->hiddenKeyboard());

            return null;
        }

        return $user;
    }

    private function linkedWelcome(User $user): string
    {
        return \sprintf(
            "You are linked as %s.\n\nSend me an https:// video link or use /help.",
            $this->telegramLabel($user),
        );
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
