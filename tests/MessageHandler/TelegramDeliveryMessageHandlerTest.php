<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\DownloadTask;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Message\TelegramDeliveryMessage;
use App\MessageHandler\TelegramDeliveryMessageHandler;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\SourceRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DownloadTaskManagerInterface;
use App\Service\TelegramClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TelegramDeliveryMessageHandlerTest extends TestCase
{
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if (null === $this->directory || !\is_dir($this->directory)) {
            return;
        }

        \array_map('unlink', \glob(\sprintf('%s/*', $this->directory)) ?: []);
        \rmdir($this->directory);
    }

    /**
     * Verifies delivery and Source deletion are audited under the user's email.
     */
    public function testAuditsDeliveryAndSourceDeletionUnderUserEmail(): void
    {
        $this->directory = \sprintf(
            '%s/ytdownloader-audit-%s',
            \sys_get_temp_dir(),
            \bin2hex(\random_bytes(6)),
        );
        \mkdir($this->directory);
        $path = \sprintf('%s/video.mp4', $this->directory);
        \file_put_contents($path, 'video');

        $user = new User()->setEmail('telegram@test.local')->setTelegramUserId(12_345);

        $task = new DownloadTask();

        $task->setUrl('https://test.local/video')->setQuality('best')
            ->setOrigin(DownloadTask::ORIGIN_TELEGRAM)
            ->setTelegramChatId(12_345)
            ->setUser($user)
            ->setDeliveryStatus(DownloadTask::DELIVERY_PENDING);
        (new \ReflectionProperty(DownloadTask::class, 'id'))->setValue($task, 7);

        $source = new Source();
        $source->setFilepath($this->directory)->setFilename('video.mp4')->setDownloadTask($task);

        $taskRepository = $this->createStub(DownloadTaskRepositoryInterface::class);
        $taskRepository->method('findById')->willReturn($task);

        $sourceRepository = $this->createStub(SourceRepositoryInterface::class);
        $sourceRepository->method('findByDownloadTask')->willReturn([$source]);

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendVideo')
            ->with(12_345, $path, 'video.mp4 · best')
            ->willReturn(99);

        $taskManager = $this->createMock(DownloadTaskManagerInterface::class);
        $taskManager->expects($this->once())
            ->method('markDeliveryStatus')
            ->with(7, DownloadTask::DELIVERY_SENT, 99);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($source);
        $entityManager->expects($this->once())->method('flush');

        $calls       = [];
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(static function (AuditAction $action, mixed ...$arguments) use (&$calls): void {
                $calls[] = [$action, ...$arguments];
            });

        $handler = new TelegramDeliveryMessageHandler(
            $taskRepository,
            $sourceRepository,
            $telegramClient,
            $taskManager,
            $entityManager,
            $auditLogger,
            $this->createStub(LoggerInterface::class),
            50,
            'https://test.local',
        );

        $handler->__invoke(new TelegramDeliveryMessage(7));

        $this->assertCount(2, $calls);

        $deletion = $calls[0];
        $this->assertSame(AuditAction::SOURCE_DELETE, $deletion[0]);
        $this->assertSame(AuditChannel::TELEGRAM, $deletion[1]);
        $this->assertSame(['reason' => 'telegram_delivery', 'telegram_chat_id' => 12_345], $deletion[6]);
        $this->assertNull($deletion[7]);
        $this->assertNull($deletion[8]);
        $this->assertSame($user, $deletion[9]);

        $delivery = $calls[1];
        $this->assertSame(AuditAction::TELEGRAM_DELIVERED, $delivery[0]);
        $this->assertSame(AuditChannel::TELEGRAM, $delivery[1]);
        $this->assertSame(['sent' => 1, 'failed' => 0, 'telegram_chat_id' => 12_345], $delivery[6]);
        $this->assertNull($delivery[7]);
        $this->assertNull($delivery[8]);
        $this->assertSame($user, $delivery[9]);
    }
}
