<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\RefreshTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_token')]
#[ORM\UniqueConstraint(name: 'UNIQ_REFRESH_TOKEN_TOKEN', columns: ['token'])]
#[ORM\Index(name: 'IDX_REFRESH_TOKEN_USER', columns: ['user_id'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'token', type: Types::STRING, length: 128, unique: true)]
    private ?string $token = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        if (!$this->expiresAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $this->expiresAt <= $now;
    }

    public function revoke(\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getTokenOrFail(): string
    {
        return $this->token ?? throw PropertyIsNotSetException::forProperty('token');
    }

    public function getUserOrFail(): User
    {
        return $this->user ?? throw PropertyIsNotSetException::forProperty('user');
    }

    public function getCreatedAtOrFail(): \DateTimeImmutable
    {
        return $this->createdAt ?? throw PropertyIsNotSetException::forProperty('createdAt');
    }

    public function getExpiresAtOrFail(): \DateTimeImmutable
    {
        return $this->expiresAt ?? throw PropertyIsNotSetException::forProperty('expiresAt');
    }
}
