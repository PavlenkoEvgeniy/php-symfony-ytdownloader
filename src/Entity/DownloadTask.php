<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\DownloadTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: DownloadTaskRepository::class)]
#[ORM\Table(name: 'download_task')]
#[ORM\Index(name: 'idx_download_task_status', columns: ['status'])]
class DownloadTask
{
    use TimestampableEntity;
    public const string STATUS_QUEUED     = 'queued';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_SUCCESS    = 'success';
    public const string STATUS_ERROR      = 'error';

    public const string ORIGIN_UI       = 'ui';
    public const string ORIGIN_API      = 'api';
    public const string ORIGIN_TELEGRAM = 'telegram';

    public const string DELIVERY_PENDING = 'pending';
    public const string DELIVERY_SENT    = 'sent';
    public const string DELIVERY_FAILED  = 'failed';
    public const string DELIVERY_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 512)]
    private ?string $url = null;

    #[ORM\Column(name: 'quality', type: Types::STRING, length: 32)]
    private ?string $quality = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 32)]
    private ?string $status = self::STATUS_QUEUED;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'attempts', type: Types::INTEGER, options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'origin', type: Types::STRING, length: 16, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(name: 'telegram_chat_id', type: Types::BIGINT, nullable: true)]
    private ?int $telegramChatId = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(name: 'delivery_status', type: Types::STRING, length: 16, nullable: true)]
    private ?string $deliveryStatus = null;

    #[ORM\Column(name: 'delivered_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(name: 'telegram_message_id', type: Types::BIGINT, nullable: true)]
    private ?int $telegramMessageId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getQuality(): ?string
    {
        return $this->quality;
    }

    public function setQuality(string $quality): static
    {
        $this->quality = $quality;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getTelegramChatId(): ?int
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?int $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDeliveryStatus(): ?string
    {
        return $this->deliveryStatus;
    }

    public function setDeliveryStatus(?string $deliveryStatus): static
    {
        $this->deliveryStatus = $deliveryStatus;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getTelegramMessageId(): ?int
    {
        return $this->telegramMessageId;
    }

    public function setTelegramMessageId(?int $telegramMessageId): static
    {
        $this->telegramMessageId = $telegramMessageId;

        return $this;
    }

    public function isTelegramOrigin(): bool
    {
        return self::ORIGIN_TELEGRAM === $this->origin;
    }

    public function getErrorMessageOrFail(): string
    {
        return $this->errorMessage ?? throw PropertyIsNotSetException::forProperty('errorMessage');
    }

    public function getStartedAtOrFail(): \DateTimeImmutable
    {
        return $this->startedAt ?? throw PropertyIsNotSetException::forProperty('startedAt');
    }

    public function getFinishedAtOrFail(): \DateTimeImmutable
    {
        return $this->finishedAt ?? throw PropertyIsNotSetException::forProperty('finishedAt');
    }

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getUrlOrFail(): string
    {
        return $this->url ?? throw PropertyIsNotSetException::forProperty('url');
    }

    public function getQualityOrFail(): string
    {
        return $this->quality ?? throw PropertyIsNotSetException::forProperty('quality');
    }

    public function getStatusOrFail(): string
    {
        return $this->status ?? throw PropertyIsNotSetException::forProperty('status');
    }

    public function getCreatedAtOrFail(): \DateTimeInterface
    {
        return $this->getCreatedAt() ?? throw PropertyIsNotSetException::forProperty('createdAt');
    }

    public function getUpdatedAtOrFail(): \DateTimeInterface
    {
        return $this->getUpdatedAt() ?? throw PropertyIsNotSetException::forProperty('updatedAt');
    }

    public function getTelegramChatIdOrFail(): int
    {
        return $this->telegramChatId ?? throw PropertyIsNotSetException::forProperty('telegramChatId');
    }
}
