<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Repository\AuditEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditEventRepository::class)]
#[ORM\Table(name: 'audit_event')]
#[ORM\Index(name: 'idx_audit_event_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_audit_event_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_event_actor', columns: ['actor_id'])]
class AuditEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Column(name: 'action', type: Types::STRING, length: 32, enumType: AuditAction::class)]
        private AuditAction $action,
        #[ORM\Column(name: 'channel', type: Types::STRING, length: 16, enumType: AuditChannel::class)]
        private AuditChannel $channel,
        #[ORM\Column(name: 'actor_type', type: Types::STRING, length: 16, enumType: AuditActorType::class)]
        private AuditActorType $actorType,
        #[ORM\Column(name: 'success', type: Types::BOOLEAN)]
        private bool $success,
        #[ORM\Column(name: 'actor_id', type: Types::INTEGER, nullable: true)]
        private ?int $actorId = null,
        #[ORM\Column(name: 'actor_label', type: Types::STRING, length: 255, nullable: true)]
        private ?string $actorLabel = null,
        #[ORM\Column(name: 'target_type', type: Types::STRING, length: 32, nullable: true)]
        private ?string $targetType = null,
        #[ORM\Column(name: 'target_id', type: Types::INTEGER, nullable: true)]
        private ?int $targetId = null,
        #[ORM\Column(name: 'target_label', type: Types::STRING, length: 512, nullable: true)]
        private ?string $targetLabel = null,
        #[ORM\Column(name: 'ip', type: Types::STRING, length: 45, nullable: true)]
        private ?string $ip = null,
        #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 512, nullable: true)]
        private ?string $userAgent = null,
        #[ORM\Column(name: 'request_id', type: Types::STRING, length: 64, nullable: true)]
        private ?string $requestId = null,
        /** @var array<string, mixed>|null */
        #[ORM\Column(name: 'metadata', type: Types::JSON, nullable: true)]
        private ?array $metadata = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getAction(): string
    {
        return $this->action->value;
    }

    public function getChannel(): string
    {
        return $this->channel->value;
    }

    public function getActorType(): string
    {
        return $this->actorType->value;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getActorLabel(): ?string
    {
        return $this->actorLabel;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function getTargetLabel(): ?string
    {
        return $this->targetLabel;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getMetadataJson(): ?string
    {
        if (null === $this->metadata) {
            return null;
        }

        return (string) \json_encode($this->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
