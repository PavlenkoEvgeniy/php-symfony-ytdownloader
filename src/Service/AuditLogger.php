<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditEvent;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class AuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        AuditAction $action,
        ?AuditChannel $channel = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetLabel = null,
        bool $success = true,
        array $metadata = [],
        ?AuditActorType $actorType = null,
        ?string $actorLabel = null,
        ?User $actor = null,
    ): void {
        $request                                            = $this->requestStack->getMainRequest();
        [$resolvedActorType, $actorId, $resolvedActorLabel] = $this->resolveActor($actor, $actorType, $actorLabel);

        $event = new AuditEvent(
            occurredAt: new \DateTimeImmutable(),
            action: $action,
            channel: $this->resolveChannel($channel, $request),
            actorType: $resolvedActorType,
            success: $success,
            actorId: $actorId,
            actorLabel: $resolvedActorLabel,
            targetType: $targetType,
            targetId: $targetId,
            targetLabel: $targetLabel,
            ip: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            requestId: $request?->attributes->getString('_audit_request_id') ?: null,
            metadata: [] === $metadata ? null : $metadata,
        );

        $this->entityManager->persist($event);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            // An audit failure must not break the action being audited.
            $this->logger->error('Failed to write audit event', [
                'action' => $action->value,
                'error'  => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{AuditActorType, int|null, string|null}
     */
    private function resolveActor(?User $actor, ?AuditActorType $actorType, ?string $actorLabel): array
    {
        if ($actor instanceof User) {
            return [AuditActorType::USER, $actor->getId(), $actor->getEmail()];
        }

        if ($actorType instanceof AuditActorType) {
            return [$actorType, null, $actorLabel];
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user instanceof User) {
            return [AuditActorType::USER, $user->getId(), $user->getEmail()];
        }

        if (null !== $this->requestStack->getMainRequest()) {
            return [AuditActorType::ANONYMOUS, null, null];
        }

        return [AuditActorType::SYSTEM, null, null];
    }

    private function resolveChannel(?AuditChannel $channel, ?Request $request): AuditChannel
    {
        if ($channel instanceof AuditChannel) {
            return $channel;
        }

        if (!$request instanceof Request) {
            return AuditChannel::SYSTEM;
        }

        $path = $request->getPathInfo();

        return match (true) {
            \str_starts_with($path, '/api/v1')   => AuditChannel::API_V1,
            \str_starts_with($path, '/api/v2')   => AuditChannel::API_V2,
            \str_starts_with($path, '/telegram') => AuditChannel::TELEGRAM,
            \str_starts_with($path, '/admin')    => AuditChannel::ADMIN,
            default                              => AuditChannel::UI,
        };
    }
}
