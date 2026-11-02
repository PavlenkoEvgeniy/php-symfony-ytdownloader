<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\LogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogRepository::class)]
#[ORM\Table(name: 'log')]
class Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 255)]
    private ?string $type = null;

    #[ORM\Column(name: 'message', type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'size', type: Types::FLOAT, nullable: true)]
    private ?float $size = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSize(): ?float
    {
        return $this->size;
    }

    public function setSize(?float $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getTypeOrFail(): string
    {
        return $this->type ?? throw PropertyIsNotSetException::forProperty('type');
    }

    public function getMessageOrFail(): string
    {
        return $this->message ?? throw PropertyIsNotSetException::forProperty('message');
    }

    public function getCreatedAtOrFail(): \DateTimeImmutable
    {
        return $this->createdAt ?? throw PropertyIsNotSetException::forProperty('createdAt');
    }
}
