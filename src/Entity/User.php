<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Table(name: '"user"')]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_ADMIN              = 'ROLE_ADMIN';
    public const int    DEFAULT_PASSWORD_LENGTH = 16;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 180)]
    private ?string $email = null;

    /**
     * @var string[]
     */
    #[ORM\Column(name: 'roles', type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(name: 'password', type: Types::STRING)]
    private ?string $password = null;

    #[ORM\Column(name: 'is_enabled', type: Types::BOOLEAN, options: ['default' => true])]
    private ?bool $isEnabled = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'avatar', type: Types::STRING, length: 255, nullable: true)]
    private ?string $avatar = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return null === $this->email ? null : \mb_strtolower($this->email);
    }

    public function setEmail(string $email): static
    {
        $this->email = \mb_strtolower($email);

        return $this;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    #[\Override]
    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return \array_unique($roles);
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Override]
    public function eraseCredentials(): void
    {
    }

    public static function generatePassword(int $length = self::DEFAULT_PASSWORD_LENGTH): string
    {
        return \substr(\str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        if (null === $this->avatar || '' === $this->avatar) {
            return null;
        }

        if (false !== \strpos($this->avatar, '/')) {
            return $this->avatar;
        }

        return \sprintf('/uploads/avatars/%s', $this->avatar);
    }

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getEmailOrFail(): string
    {
        return $this->email ?? throw PropertyIsNotSetException::forProperty('email');
    }

    public function getPasswordOrFail(): string
    {
        return $this->password ?? throw PropertyIsNotSetException::forProperty('password');
    }

    public function getIsEnabledOrFail(): bool
    {
        return $this->isEnabled ?? throw PropertyIsNotSetException::forProperty('isEnabled');
    }

    public function getCreatedAtOrFail(): \DateTimeImmutable
    {
        return $this->createdAt ?? throw PropertyIsNotSetException::forProperty('createdAt');
    }
}
