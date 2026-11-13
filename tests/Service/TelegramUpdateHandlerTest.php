<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\TelegramProbeResult;
use App\Entity\DownloadTask;
use App\Entity\User;
use App\Enum\AuditChannel;
use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\DownloadDispatcherInterface;
use App\Service\DownloadRetryServiceInterface;
use App\Service\DownloadSizeEstimatorInterface;
use App\Service\FormatResolver;
use App\Service\TelegramClientInterface;
use App\Service\TelegramUpdateHandler;
use App\Service\YoutubeDlProbeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class TelegramUpdateHandlerTest extends TestCase
{
    /**
     * Verifies /help lists the commands and keeps the reply keyboard hidden.
     */
    public function testHelpRemovesKeyboard(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(
                42,
                $this->stringContains('/start'),
                ['remove_keyboard' => true],
            );

        $this->handler(client: $client)->handle($this->message('/help'));
    }

    /**
     * Verifies a Telegram download is refused when the disk is under pressure.
     */
    public function testCallbackRejectsDownloadUnderDiskPressure(): void
    {
        $token = \str_repeat('a', 32);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_pending_42')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 42,
            ],
        ));
        $cache->expects($this->once())->method('deleteItem')->with('telegram_pending_42');

        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $dispatcher = $this->createStub(DownloadDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(InsufficientDiskSpaceException::create(0.0, 1));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', $this->stringContains('free disk space'));
        $client->expects($this->never())->method('editMessageText');

        $handler = $this->handler(
            users: $users,
            dispatcher: $dispatcher,
            client: $client,
            cache: $cache,
        );

        $handler->handle($this->callbackUpdate(\sprintf('dl:%s:best', $token)));
    }

    /**
     * Verifies the Re-download button requeues the user's error task.
     */
    public function testRetryCallbackRequeuesTheUsersErrorTask(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(42)
            ->setStatus(DownloadTask::STATUS_ERROR)
            ->setUser($user);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findById')->willReturn($task);

        $retry = $this->createMock(DownloadRetryServiceInterface::class);
        $retry->expects($this->once())
            ->method('reDownload')
            ->with(7, $user, AuditChannel::TELEGRAM)
            ->willReturn(true);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'Queued again.');

        $this->handler(users: $users, tasks: $tasks, retry: $retry, client: $client)
            ->handle($this->callbackUpdate('retry:7'));
    }

    /**
     * Verifies an update without a message is ignored.
     */
    public function testUpdateWithoutMessageIsIgnored(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $client->expects($this->never())->method('answerCallbackQuery');

        $this->handler(client: $client)->handle(['edited_message' => ['text' => 'hi']]);
    }

    /**
     * Verifies a non-array message payload is ignored.
     */
    public function testNonArrayMessageIsIgnored(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->never())->method('sendMessage');

        $this->handler(client: $client)->handle(['message' => 'not-an-array']);
    }

    /**
     * Verifies an update without a chat or sender id is ignored.
     */
    public function testUpdateWithoutChatOrUserIsIgnored(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->never())->method('sendMessage');

        $this->handler(client: $client)->handle($this->message('/help', chatId: 0, userId: 0));
    }

    /**
     * Verifies the bot refuses to work in group chats.
     */
    public function testGroupChatIsRejected(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'I work only in private chats.');

        $this->handler(client: $client)->handle($this->message('hello', chatType: 'group'));
    }

    /**
     * Verifies plain text without a link asks for a video link.
     */
    public function testPlainTextAsksForALink(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Send me an https://'), ['remove_keyboard' => true]);

        $this->handler(client: $client)->handle($this->message('just some text'));
    }

    /**
     * Verifies a command mention of the bot is stripped before matching.
     */
    public function testCommandWithBotSuffixIsRecognized(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Commands:'));

        $this->handler(client: $client)->handle($this->message('/HELP@my_bot'));
    }

    /**
     * Verifies a linked and enabled user gets the linked welcome message.
     */
    public function testStartWelcomesLinkedUser(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setTelegramUsername('jane')->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('You are linked as @jane'), ['remove_keyboard' => true]);

        $this->handler(users: $users, client: $client)->handle($this->message('/start'));
    }

    /**
     * Verifies /start falls back to the numeric account label when no username is stored.
     */
    public function testStartWelcomesLinkedUserWithoutUsername(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Telegram user 42'));

        $this->handler(users: $users, client: $client)->handle($this->message('/start'));
    }

    /**
     * Verifies a disabled account is not treated as linked on /start.
     */
    public function testStartTreatsDisabledAccountAsUnlinked(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(false);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Link your account first with /login.'));

        $this->handler(users: $users, client: $client)->handle($this->message('/start'));
    }

    /**
     * Verifies /start invites an unknown sender to link the account.
     */
    public function testStartAsksToLogin(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Link your account first with /login.'), ['remove_keyboard' => true]);

        $this->handler(client: $client)->handle($this->message('/start'));
    }

    /**
     * Verifies /login refuses to issue a token for an already linked sender.
     */
    public function testLoginRefusesAlreadyLinkedSender(): void
    {
        $existing = new User();

        $existing->setTelegramUserId(42)->setTelegramUsername('jane')->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($existing);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('save');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('Already linked as @jane. Use /logout to unlink.'));

        $this->handler(users: $users, client: $client, cache: $cache)->handle($this->message('/login'));
    }

    /**
     * Verifies /login stores a link token and sends the confirmation URL.
     */
    public function testLoginStoresLinkTokenAndSendsUrl(): void
    {
        $stored = null;
        $item   = $this->cacheItem($stored);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringStartsWith('telegram_link_'))
            ->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->callback(static function (string $text): bool {
                return \str_contains($text, 'https://test.local/telegram/link?token=')
                    && \str_contains($text, 'expires in 10 minutes');
            }), ['remove_keyboard' => true]);

        $this->handler(client: $client, cache: $cache)->handle($this->message('/login', from: ['id' => 42, 'username' => 'jane']));

        $this->assertIsArray($stored);
        $this->assertSame(42, $stored['telegram_user_id']);
        $this->assertSame('jane', $stored['telegram_username']);
    }

    /**
     * Verifies /login tolerates a sender without a username.
     */
    public function testLoginStoresNullUsername(): void
    {
        $stored = null;
        $item   = $this->cacheItem($stored);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects($this->once())->method('save');

        $client = $this->createStub(TelegramClientInterface::class);

        $this->handler(client: $client, cache: $cache)->handle($this->message('/login'));

        $this->assertIsArray($stored);
        $this->assertNull($stored['telegram_username']);
    }

    /**
     * Verifies /logout cancels queued downloads, drops the pending selection and unlinks.
     */
    public function testLogoutUnlinksAndCancelsQueuedDownloads(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setTelegramUsername('jane')->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->once())->method('deleteQueuedByUser')->with($user)->willReturn(2);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('deleteItem')->with('telegram_pending_42');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'Unlinked. Queued downloads were cancelled.', ['remove_keyboard' => true]);

        $this->handler(
            users: $users,
            tasks: $tasks,
            client: $client,
            cache: $cache,
            entityManager: $entityManager,
        )->handle($this->message('/logout'));

        $this->assertFalse($user->isTelegramLinked());
    }

    /**
     * Verifies /logout for an unlinked sender stays silent.
     */
    public function testLogoutWithoutLinkSendsTheLinkingHintOnly(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('not linked. Use /login'), ['remove_keyboard' => true]);

        $this->handler(client: $client)->handle($this->message('/logout'));
    }

    /**
     * Verifies /cancel reports how many queued downloads were removed.
     */
    public function testCancelReportsRemovedCount(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->once())->method('deleteQueuedByUser')->with($user)->willReturn(3);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('deleteItem')->with('telegram_pending_42');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'Cancelled 3 queued download(s).', ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client, cache: $cache)
            ->handle($this->message('/cancel'));
    }

    /**
     * Verifies /cancel with an empty queue says there is nothing to cancel.
     */
    public function testCancelWithEmptyQueueSaysNothingToCancel(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('deleteQueuedByUser')->willReturn(0);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'Nothing to cancel.', ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)->handle($this->message('/cancel'));
    }

    /**
     * Verifies /status with an empty active list reports no downloads.
     */
    public function testStatusWithoutActiveTasks(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->any())
            ->method('findActiveByUser')
            ->with($user)
            ->willReturn([]);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'No active downloads.', ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)->handle($this->message('/status'));
    }

    /**
     * Verifies /status lists every active task with its status, quality and url.
     */
    public function testStatusListsActiveTasks(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')->setStatus(DownloadTask::STATUS_PROCESSING);
        new \ReflectionProperty(DownloadTask::class, 'id')->setValue($task, 7);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUser')->willReturn([$task]);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('#7 processing · best · https://test.local/video'), ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)->handle($this->message('/status'));
    }

    /**
     * Verifies a disabled account cannot act on a link or a command.
     */
    public function testDisabledAccountIsRejected(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(false);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'This account is disabled.', ['remove_keyboard' => true]);

        $this->handler(users: $users, client: $client)->handle($this->message('https://test.local/video'));
    }

    /**
     * Verifies a link from an unlinked sender is answered with the linking hint.
     */
    public function testLinkFromUnlinkedSenderIsRejected(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('not linked. Use /login'), ['remove_keyboard' => true]);

        $this->handler(client: $client)->handle($this->message('https://test.local/video'));
    }

    /**
     * Verifies an overlong link is rejected before probing.
     */
    public function testOverlongLinkIsRejected(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $probe = $this->createMock(YoutubeDlProbeInterface::class);
        $probe->expects($this->never())->method('probe');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'The link is too long.', ['remove_keyboard' => true]);

        $this->handler(users: $users, client: $client, probe: $probe)
            ->handle($this->message(\sprintf('https://test.local/%s', \str_repeat('a', 520))));
    }

    /**
     * Verifies a link already queued by the user is not queued twice.
     */
    public function testAlreadyQueuedLinkIsRejected(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $queued = new DownloadTask();

        $queued->setUrl('https://test.local/video')->setQuality('best')->setStatus(DownloadTask::STATUS_QUEUED);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->any())
            ->method('findActiveByUserAndUrl')
            ->with($user, 'https://test.local/video')
            ->willReturn($queued);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'This link is already queued.', ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)
            ->handle($this->message('https://test.local/video'));
    }

    /**
     * Verifies the concurrent download limit is enforced.
     */
    public function testTooManyActiveDownloadsAreRejected(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->any())->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->expects($this->any())->method('countActiveByUser')->with($user)->willReturn(3);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'You already have 3 downloads in progress.', ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)
            ->handle($this->message('https://test.local/video'));
    }

    /**
     * Verifies a failed probe is logged and reported to the sender.
     */
    public function testProbeFailureIsReported(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->method('countActiveByUser')->willReturn(0);

        $probe = $this->createStub(YoutubeDlProbeInterface::class);
        $probe->method('probe')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Telegram link probe failed', $this->arrayHasKey('error'));

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(users: $users, tasks: $tasks, client: $client, probe: $probe, logger: $logger)
            ->handle($this->message('https://test.local/video'));

        $this->assertCount(2, $messages);
        $this->assertSame('Could not read this link.', $messages[1]['text']);
    }

    /**
     * Verifies a playlist link is refused.
     */
    public function testPlaylistLinkIsRejected(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->method('countActiveByUser')->willReturn(0);

        $probe = $this->createStub(YoutubeDlProbeInterface::class);
        $probe->method('probe')->willReturn(new TelegramProbeResult(isPlaylist: true, title: 'Playlist'));

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('save');

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(users: $users, tasks: $tasks, client: $client, probe: $probe, cache: $cache)
            ->handle($this->message('https://test.local/playlist'));

        $this->assertCount(2, $messages);
        $this->assertSame('Playlists are not supported. Send a link to a single video.', $messages[1]['text']);
    }

    /**
     * Verifies a valid link stores the pending choice and offers the quality buttons.
     */
    public function testValidLinkStoresPendingChoiceAndOffersQualities(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->method('countActiveByUser')->willReturn(0);

        $probe = $this->createStub(YoutubeDlProbeInterface::class);
        $probe->method('probe')->willReturn(new TelegramProbeResult(isPlaylist: false, title: 'My Video', duration: 3_725));

        $stored = null;
        $item   = $this->cacheItem($stored);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_pending_42')->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(users: $users, tasks: $tasks, client: $client, probe: $probe, cache: $cache)
            ->handle($this->message('https://test.local/video'));

        $this->assertCount(2, $messages);
        $this->assertSame('Checking link…', $messages[0]['text']);
        $this->assertSame("My Video (1:02:05)\n\nChoose quality:", $messages[1]['text']);

        $keyboard = $messages[1]['replyMarkup']['inline_keyboard'];

        $this->assertIsArray($stored);
        $this->assertSame('https://test.local/video', $stored['url']);
        $this->assertSame('My Video', $stored['title']);
        $this->assertSame(42, $stored['telegram_user_id']);

        $token = (string) $stored['token'];
        $this->assertSame(32, \strlen($token));

        $this->assertCount(2, $keyboard);
        $this->assertSame(
            ['text' => 'Best', 'callback_data' => \sprintf('dl:%s:best', $token)],
            $keyboard[0][0],
        );
        $this->assertSame(
            ['text' => 'Audio', 'callback_data' => \sprintf('dl:%s:audio', $token)],
            $keyboard[1][1],
        );
    }

    /**
     * Verifies a short video is captioned as minutes and seconds.
     */
    public function testShortDurationIsFormattedAsMinutesAndSeconds(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->method('countActiveByUser')->willReturn(0);

        $probe = $this->createStub(YoutubeDlProbeInterface::class);
        $probe->method('probe')->willReturn(new TelegramProbeResult(isPlaylist: false, title: 'Clip', duration: 125));

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem());

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(users: $users, tasks: $tasks, client: $client, probe: $probe, cache: $cache)
            ->handle($this->message('https://test.local/clip'));

        $this->assertCount(2, $messages);
        $this->assertStringStartsWith('Clip (2:05)', $messages[1]['text']);
    }

    /**
     * Verifies a video without a duration is captioned by its title alone.
     */
    public function testMissingDurationIsOmittedFromTheCaption(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findActiveByUserAndUrl')->willReturn(null);
        $tasks->method('countActiveByUser')->willReturn(0);

        $probe = $this->createStub(YoutubeDlProbeInterface::class);
        $probe->method('probe')->willReturn(new TelegramProbeResult(isPlaylist: false, title: 'Clip'));

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem());

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(users: $users, tasks: $tasks, client: $client, probe: $probe, cache: $cache)
            ->handle($this->message('https://test.local/clip'));

        $this->assertCount(2, $messages);
        $this->assertStringStartsWith('Clip', $messages[1]['text']);
        $this->assertStringNotContainsString('(', $messages[1]['text']);
    }

    /**
     * Verifies an incomplete callback payload is ignored.
     */
    public function testCallbackWithoutChatIsIgnored(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->never())->method('answerCallbackQuery');

        $this->handler(client: $client)->handle([
            'callback_query' => [
                'id'   => 'callback-1',
                'data' => 'retry:7',
                'from' => ['id' => 42],
            ],
        ]);
    }

    /**
     * Verifies an unknown callback action is answered.
     */
    public function testCallbackWithUnknownActionIsAnswered(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'Unknown action.');

        $this->handler(client: $client)->handle($this->callbackUpdate('dl:not-a-valid-payload:best'));
    }

    /**
     * Verifies a stale quality selection asks the sender to send the link again.
     */
    public function testCallbackWithExpiredSelectionIsAnswered(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_pending_42')->willReturn($this->cacheItem());

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'This selection has expired. Send the link again.');

        $this->handler(client: $client, cache: $cache)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:best', \str_repeat('a', 32))));
    }

    /**
     * Verifies a selection made by another Telegram account is refused.
     */
    public function testCallbackFromAnotherTelegramAccountIsRefused(): void
    {
        $token = \str_repeat('a', 32);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 99,
            ],
        ));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'This selection has expired. Send the link again.');

        $this->handler(client: $client, cache: $cache)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:best', $token)));
    }

    /**
     * Verifies a quality selection from an unlinked sender is refused.
     */
    public function testCallbackFromUnlinkedSenderIsRefused(): void
    {
        $token = \str_repeat('a', 32);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 42,
            ],
        ));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'Please link your account first.');

        $this->handler(client: $client, cache: $cache)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:audio', $token)));
    }

    /**
     * Verifies a successful selection dispatches the download and rewrites the message.
     */
    public function testCallbackQueuesTheDownload(): void
    {
        $token = \str_repeat('a', 32);

        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 42,
            ],
        ));
        $cache->expects($this->once())->method('deleteItem')->with('telegram_pending_42');

        $dispatcher = $this->createMock(DownloadDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                'https://test.local/video',
                'poor',
                $user,
                DownloadTask::ORIGIN_TELEGRAM,
                42,
                'Test',
            )
            ->willReturn(new DownloadTask());

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())->method('answerCallbackQuery')->with('callback-1', 'Queued');
        $client->expects($this->once())
            ->method('editMessageText')
            ->with(42, 7, 'Queued: Test (poor)');

        $this->handler(users: $users, client: $client, cache: $cache, dispatcher: $dispatcher)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:poor', $token)));
    }

    /**
     * Verifies a dispatch failure is logged and answered without breaking the webhook.
     */
    public function testCallbackDispatchFailureIsAnswered(): void
    {
        $token = \str_repeat('a', 32);

        $user = new User();
        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 42,
            ],
        ));

        $dispatcher = $this->createStub(DownloadDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new \RuntimeException('queue is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to queue a Telegram download', $this->arrayHasKey('error'));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'Could not queue the download.');
        $client->expects($this->never())->method('editMessageText');

        $this->handler(users: $users, client: $client, cache: $cache, dispatcher: $dispatcher, logger: $logger)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:best', $token)));
    }

    /**
     * Verifies an edit failure only degrades the confirmation message.
     */
    public function testCallbackEditFailureIsLogged(): void
    {
        $token = \str_repeat('a', 32);

        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $cache = $this->createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->cacheItem(
            isHit: true,
            value: [
                'token'            => $token,
                'url'              => 'https://test.local/video',
                'title'            => 'Test',
                'telegram_user_id' => 42,
            ],
        ));

        $dispatcher = $this->createStub(DownloadDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturn(new DownloadTask());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Failed to edit the Telegram quality message', $this->arrayHasKey('error'));

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())->method('answerCallbackQuery')->with('callback-1', 'Queued');
        $client->method('editMessageText')->willThrowException(new \RuntimeException('message is too old'));

        $this->handler(users: $users, client: $client, cache: $cache, dispatcher: $dispatcher, logger: $logger)
            ->handle($this->callbackUpdate(\sprintf('dl:%s:best', $token)));
    }

    /**
     * Verifies a retry of a task that no longer exists is refused.
     */
    public function testRetryOfMissingTaskIsRefused(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findById')->willReturn(null);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'This download cannot be retried.');

        $this->handler(users: $users, tasks: $tasks, client: $client)->handle($this->callbackUpdate('retry:7'));
    }

    /**
     * Verifies a finished task cannot be retried.
     */
    public function testRetryOfFinishedTaskIsRefused(): void
    {
        $user = new User();

        $user->setTelegramUserId(42)->setIsEnabled(true);

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setStatus(DownloadTask::STATUS_SUCCESS)
            ->setUser($user);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findById')->willReturn($task);

        $retry = $this->createMock(DownloadRetryServiceInterface::class);
        $retry->expects($this->never())->method('reDownload');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'This download cannot be retried.');

        $this->handler(users: $users, tasks: $tasks, retry: $retry, client: $client)
            ->handle($this->callbackUpdate('retry:7'));
    }

    /**
     * Verifies a refused requeue is reported back to the callback.
     */
    public function testRetryReportsRefusedRequeue(): void
    {
        $user = new User();
        $user->setTelegramUserId(42)->setIsEnabled(true);

        $task = new DownloadTask();
        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setStatus(DownloadTask::STATUS_ERROR)
            ->setUser($user);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn($user);

        $tasks = $this->createStub(DownloadTaskRepositoryInterface::class);
        $tasks->method('findById')->willReturn($task);

        $retry = $this->createStub(DownloadRetryServiceInterface::class);
        $retry->method('reDownload')->willReturn(false);

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback-1', 'This download cannot be retried.');

        $this->handler(users: $users, tasks: $tasks, retry: $retry, client: $client)
            ->handle($this->callbackUpdate('retry:7'));
    }

    /**
     * Verifies a retry from an unlinked sender is answered with the linking hint.
     */
    public function testRetryFromUnlinkedSenderIsAnsweredWithTheHint(): void
    {
        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('not linked. Use /login'), ['remove_keyboard' => true]);
        $client->expects($this->never())->method('answerCallbackQuery');

        $this->handler(client: $client)->handle($this->callbackUpdate('retry:7'));
    }

    /**
     * Verifies /cancel from an unlinked sender only asks to link the account.
     */
    public function testCancelFromUnlinkedSenderOnlyAsksToLink(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn(null);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->never())->method('deleteQueuedByUser');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('not linked. Use /login'), ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)
            ->handle($this->message('/cancel'));
    }

    /**
     * Verifies /status from an unlinked sender only asks to link the account.
     */
    public function testStatusFromUnlinkedSenderOnlyAsksToLink(): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findOneByTelegramUserId')->willReturn(null);

        $tasks = $this->createMock(DownloadTaskRepositoryInterface::class);
        $tasks->expects($this->never())->method('findActiveByUser');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, $this->stringContains('not linked. Use /login'), ['remove_keyboard' => true]);

        $this->handler(users: $users, tasks: $tasks, client: $client)
            ->handle($this->message('/status'));
    }

    /**
     * Verifies the offered selection stores a fresh pending token and leaves
     * out the rejected quality and qualities estimated above the limit, while
     * a quality without an estimate is still offered.
     */
    public function testOfferQualitySelectionFiltersRejectedAndOverLimitQualities(): void
    {
        $estimator = $this->createStub(DownloadSizeEstimatorInterface::class);
        $estimator->method('estimate')->willReturnCallback(
            static function (string $url, string $format): array {
                if (\str_contains($format, 'height<=1080')) {
                    return [3_000_000_000];
                }

                if (\str_contains($format, 'height<=720')) {
                    return [3_000_000_000];
                }

                if (\str_contains($format, 'height<=320')) {
                    return [];
                }

                if (\str_starts_with($format, 'bestaudio')) {
                    return [50_000_000];
                }

                return [];
            },
        );

        $stored = null;
        $item   = $this->cacheItem($stored);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->with('telegram_pending_42')->willReturn($item);
        $cache->expects($this->once())->method('save')->with($item);

        $messages = [];
        $client   = $this->captureMessages($messages);

        $this->handler(client: $client, cache: $cache, sizeEstimator: $estimator)
            ->offerQualitySelection(42, 42, 'https://test.local/video', 'Test video', 'best');

        $this->assertCount(1, $messages);
        $this->assertSame('Choose quality:', $messages[0]['text']);

        $this->assertIsArray($stored);
        $this->assertSame('https://test.local/video', $stored['url']);
        $this->assertSame('Test video', $stored['title']);
        $this->assertSame(42, $stored['telegram_user_id']);

        $token    = (string) $stored['token'];
        $keyboard = $messages[0]['replyMarkup']['inline_keyboard'];

        // "best" was rejected, "moderate" is over the limit: only the
        // unknown-estimate "poor" and the small "audio" remain.
        $this->assertSame(
            [
                ['text' => 'Poor', 'callback_data' => \sprintf('dl:%s:poor', $token)],
                ['text' => 'Audio', 'callback_data' => \sprintf('dl:%s:audio', $token)],
            ],
            \array_merge(...$keyboard),
        );
    }

    /**
     * Verifies that when every remaining quality is over the limit the bot
     * answers instead of showing an empty selection, and stores no token.
     */
    public function testOfferQualitySelectionSaysWhenNoQualityFits(): void
    {
        $estimator = $this->createStub(DownloadSizeEstimatorInterface::class);
        $estimator->method('estimate')->willReturn([4_000_000_000]);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->never())->method('getItem');
        $cache->expects($this->never())->method('save');

        $client = $this->createMock(TelegramClientInterface::class);
        $client->expects($this->once())
            ->method('sendMessage')
            ->with(42, 'No other quality fits the Telegram limit.', ['remove_keyboard' => true]);

        $this->handler(client: $client, cache: $cache, sizeEstimator: $estimator)
            ->offerQualitySelection(42, 42, 'https://test.local/video', 'Test video', 'best');
    }

    private function handler(
        ?UserRepositoryInterface $users = null,
        ?DownloadTaskRepositoryInterface $tasks = null,
        ?DownloadDispatcherInterface $dispatcher = null,
        ?DownloadRetryServiceInterface $retry = null,
        ?TelegramClientInterface $client = null,
        ?YoutubeDlProbeInterface $probe = null,
        ?CacheItemPoolInterface $cache = null,
        ?EntityManagerInterface $entityManager = null,
        ?LoggerInterface $logger = null,
        string $hostUrl = 'https://test.local',
        ?DownloadSizeEstimatorInterface $sizeEstimator = null,
        int $telegramMaxUploadMb = 2048,
    ): TelegramUpdateHandler {
        return new TelegramUpdateHandler(
            $users ?? $this->createStub(UserRepositoryInterface::class),
            $tasks ?? $this->createStub(DownloadTaskRepositoryInterface::class),
            $dispatcher ?? $this->createStub(DownloadDispatcherInterface::class),
            $retry ?? $this->createStub(DownloadRetryServiceInterface::class),
            $client ?? $this->createStub(TelegramClientInterface::class),
            $probe ?? $this->createStub(YoutubeDlProbeInterface::class),
            $cache ?? $this->createStub(CacheItemPoolInterface::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            $hostUrl,
            new FormatResolver(),
            $sizeEstimator ?? $this->createStub(DownloadSizeEstimatorInterface::class),
            $telegramMaxUploadMb,
        );
    }

    /**
     * @param array<string, mixed> $from
     *
     * @return array<string, mixed>
     */
    private function message(
        string $text,
        int $chatId = 42,
        int $userId = 42,
        string $chatType = 'private',
        array $from = [],
    ): array {
        return [
            'message' => [
                'message_id' => 1,
                'chat'       => ['id' => $chatId, 'type' => $chatType],
                'from'       => $from + ['id' => $userId],
                'text'       => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackUpdate(string $data): array
    {
        return [
            'callback_query' => [
                'id'      => 'callback-1',
                'data'    => $data,
                'from'    => ['id' => 42],
                'message' => [
                    'message_id' => 7,
                    'chat'       => ['id' => 42],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $stored
     */
    private function cacheItem(?array &$stored = null, bool $isHit = false, mixed $value = null): CacheItemInterface
    {
        $item = $this->createStub(CacheItemInterface::class);

        $item->method('set')->willReturnCallback(static function (mixed $value) use (&$stored, $item): CacheItemInterface {
            $stored = $value;

            return $item;
        });
        $item->method('expiresAfter')->willReturnCallback(static fn (): CacheItemInterface => $item);
        $item->method('isHit')->willReturn($isHit);
        $item->method('get')->willReturn($value);

        return $item;
    }

    /**
     * @param array<int, array{text: string, replyMarkup: array<string, mixed>|null}> $messages
     */
    private function captureMessages(array &$messages): TelegramClientInterface
    {
        $client = $this->createStub(TelegramClientInterface::class);
        $client->method('sendMessage')->willReturnCallback(
            static function (int $chatId, string $text, ?array $replyMarkup = null) use (&$messages): int {
                $messages[] = ['text' => $text, 'replyMarkup' => $replyMarkup];

                return 1;
            },
        );

        return $client;
    }
}
