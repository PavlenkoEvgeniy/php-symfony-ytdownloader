<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\SourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\Table(name: 'source')]
class Source
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'filename', type: Types::STRING, length: 255)]
    private ?string $filename = null;

    #[ORM\Column(name: 'filepath', type: Types::STRING, length: 255)]
    private ?string $filepath = null;

    #[ORM\Column(name: 'description', type: Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'size', type: Types::FLOAT)]
    private ?float $size = null;

    #[ORM\ManyToOne(targetEntity: DownloadTask::class)]
    #[ORM\JoinColumn(name: 'download_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DownloadTask $downloadTask = null;

    #[ORM\Column(name: 'origin', type: Types::STRING, length: 16, nullable: true)]
    private ?string $origin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilepath(): ?string
    {
        return $this->filepath;
    }

    public function setFilepath(string $filepath): static
    {
        $this->filepath = $filepath;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSize(): ?float
    {
        return $this->size;
    }

    public function setSize(float $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getDownloadTask(): ?DownloadTask
    {
        return $this->downloadTask;
    }

    public function setDownloadTask(?DownloadTask $downloadTask): static
    {
        $this->downloadTask = $downloadTask;

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

    public function isTelegramOrigin(): bool
    {
        return DownloadTask::ORIGIN_TELEGRAM === $this->origin;
    }

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getFilenameOrFail(): string
    {
        return $this->filename ?? throw PropertyIsNotSetException::forProperty('filename');
    }

    public function getFilepathOrFail(): string
    {
        return $this->filepath ?? throw PropertyIsNotSetException::forProperty('filepath');
    }

    public function getSizeOrFail(): float
    {
        return $this->size ?? throw PropertyIsNotSetException::forProperty('size');
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
