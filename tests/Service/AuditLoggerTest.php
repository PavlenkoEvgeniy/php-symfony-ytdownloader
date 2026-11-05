<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class AuditLoggerTest extends TestCase
{
    private ?AuditEvent $lastEvent = null;

    /**
     * Verifies explicit context and request data are stored on the event.
     */
    public function testStoresExplicitContextAndRequestMetadata(): void
    {
        $request = $this->createRequest('/ui/source/1');
        $request->attributes->set('_audit_request_id', 'request-1');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (AuditEvent $event): void {
                $this->lastEvent = $event;
            })
        ;
        $entityManager->expects($this->once())->method('flush');

        $logger = new AuditLogger(
            $entityManager,
            $this->createStub(TokenStorageInterface::class),
            $this->createStack($request),
            $this->createStub(LoggerInterface::class),
        );

        $logger->log(
            action: AuditAction::SOURCE_DOWNLOAD,
            channel: AuditChannel::TELEGRAM,
            targetType: 'source',
            targetId: 42,
            targetLabel: 'video.mp4',
            success: false,
            metadata: ['reason' => 'file_not_found'],
            actorType: AuditActorType::TELEGRAM,
            actorLabel: '12345',
        );

        $event = $this->requireLastEvent();

        $this->assertSame('telegram', $event->getChannel());
        $this->assertSame('telegram', $event->getActorType());
        $this->assertSame('12345', $event->getActorLabel());
        $this->assertNull($event->getActorId());
        $this->assertSame('source', $event->getTargetType());
        $this->assertSame(42, $event->getTargetId());
        $this->assertSame('video.mp4', $event->getTargetLabel());
        $this->assertFalse($event->isSuccess());
        $this->assertSame('192.0.2.1', $event->getIp());
        $this->assertSame('PHPUnit', $event->getUserAgent());
        $this->assertSame('request-1', $event->getRequestId());
        $this->assertSame(['reason' => 'file_not_found'], $event->getMetadata());
    }

    /**
     * Verifies an authenticated user is resolved as the actor.
     */
    public function testResolvesAuthenticatedUser(): void
    {
        $user = new User();
        $user->setEmail('admin@test.local');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $logger = $this->createCapturingLogger($tokenStorage, $this->createStack($this->createRequest('/ui/source')));

        $logger->log(action: AuditAction::SOURCE_DELETE);

        $event = $this->requireLastEvent();

        $this->assertSame('user', $event->getActorType());
        $this->assertSame('admin@test.local', $event->getActorLabel());
        $this->assertSame('ui', $event->getChannel());
        $this->assertNull($event->getMetadata());
    }

    /**
     * Verifies an explicitly passed user is used instead of the token context.
     */
    public function testUsesExplicitActorOverRequestContext(): void
    {
        $user = new User();
        $user->setEmail('telegram@test.local');

        $logger = $this->createCapturingLogger(
            $this->createStub(TokenStorageInterface::class),
            $this->createStack($this->createRequest('/telegram/hook')),
        );

        $logger->log(
            action: AuditAction::DOWNLOAD_SUCCESS,
            channel: AuditChannel::TELEGRAM,
            metadata: ['telegram_chat_id' => 42],
            actor: $user,
        );

        $event = $this->requireLastEvent();

        $this->assertSame('user', $event->getActorType());
        $this->assertSame('telegram@test.local', $event->getActorLabel());
        $this->assertNull($event->getActorId());
        $this->assertSame('telegram', $event->getChannel());
        $this->assertSame(['telegram_chat_id' => 42], $event->getMetadata());
    }

    /**
     * Verifies the channel is derived from the request path.
     */
    public function testResolvesChannelFromRequestPath(): void
    {
        $cases = [
            '/api/v1/source'      => 'api_v1',
            '/api/v2/source'      => 'api_v2',
            '/telegram/hook'      => 'telegram',
            '/admin/active-tasks' => 'admin',
            '/ui/source'          => 'ui',
        ];

        foreach ($cases as $path => $expectedChannel) {
            $logger = $this->createCapturingLogger(
                $this->createStub(TokenStorageInterface::class),
                $this->createStack($this->createRequest($path)),
            );

            $logger->log(action: AuditAction::LOGIN);

            $event = $this->requireLastEvent();

            $this->assertSame($expectedChannel, $event->getChannel(), $path);
        }
    }

    /**
     * Verifies work outside a request is attributed to the system.
     */
    public function testResolvesSystemActorAndChannelWithoutRequest(): void
    {
        $logger = $this->createCapturingLogger($this->createStub(TokenStorageInterface::class), new RequestStack());

        $logger->log(action: AuditAction::DOWNLOAD_SUCCESS);

        $event = $this->requireLastEvent();

        $this->assertSame('system', $event->getChannel());
        $this->assertSame('system', $event->getActorType());
        $this->assertNull($event->getIp());
        $this->assertNull($event->getUserAgent());
        $this->assertNull($event->getRequestId());
    }

    /**
     * Verifies an unauthenticated request is attributed to an anonymous actor.
     */
    public function testResolvesAnonymousActorForRequestWithoutToken(): void
    {
        $logger = $this->createCapturingLogger(
            $this->createStub(TokenStorageInterface::class),
            $this->createStack($this->createRequest('/ui/source/download/1')),
        );

        $logger->log(action: AuditAction::SOURCE_DOWNLOAD);

        $this->assertSame('anonymous', $this->requireLastEvent()->getActorType());
    }

    /**
     * Verifies a failing audit write is logged and does not escape.
     */
    public function testSwallowsFlushFailure(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('database is down'))
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to write audit event', ['action' => 'auth.login', 'error' => 'database is down'])
        ;

        $auditLogger = new AuditLogger(
            $entityManager,
            $this->createStub(TokenStorageInterface::class),
            new RequestStack(),
            $logger,
        );

        $auditLogger->log(action: AuditAction::LOGIN);
    }

    private function requireLastEvent(): AuditEvent
    {
        if (!$this->lastEvent instanceof AuditEvent) {
            $this->fail('No audit event was captured.');
        }

        return $this->lastEvent;
    }

    private function createCapturingLogger(TokenStorageInterface $tokenStorage, RequestStack $requestStack): AuditLogger
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')
            ->willReturnCallback(function (AuditEvent $event): void {
                $this->lastEvent = $event;
            })
        ;

        return new AuditLogger(
            $entityManager,
            $tokenStorage,
            $requestStack,
            $this->createStub(LoggerInterface::class),
        );
    }

    private function createRequest(string $path): Request
    {
        return Request::create($path, server: ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_USER_AGENT' => 'PHPUnit']);
    }

    private function createStack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
