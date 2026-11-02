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
final class DownloadTask
{
    use TimestampableEntity;
    public const string STATUS_QUEUED     = 'queued';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_SUCCESS    = 'success';
    public const string STATUS_ERROR      = 'error';

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
}
