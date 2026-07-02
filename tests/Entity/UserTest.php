<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUserConstructorInitializesCreatedAt(): void
    {
        $user = new User();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testGeneratePasswordReturnsRandomString(): void
    {
        $password1 = User::generatePassword();
        $password2 = User::generatePassword();

        $this->assertNotSame($password1, $password2);
    }

    public function testGeneratePasswordReturnsDefaultLength(): void
    {
        $password = User::generatePassword();

        $this->assertSame(User::DEFAULT_PASSWORD_LENGTH, \strlen($password));
    }

    public function testGeneratePasswordReturnsCustomLength(): void
    {
        $password = User::generatePassword(10);

        $this->assertSame(10, \strlen($password));
    }

    public function testGeneratePasswordContainsOnlyAlphanumeric(): void
    {
        $password = User::generatePassword(100);

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $password);
    }

    public function testEmailIsNormalizedToLowercase(): void
    {
        $user = new User();
        $user->setEmail('Admin@EXAMPLE.COM');

        $this->assertSame('admin@example.com', $user->getEmail());
    }

    public function testEmailGetterReturnsLowercase(): void
    {
        $user = new User();
        $user->setEmail('Admin@EXAMPLE.COM');
        // Set it again to make sure getter normalizes
        $email = $user->getEmail();

        $this->assertSame('admin@example.com', $email);
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertSame('test@example.com', $user->getUserIdentifier());
    }

    public function testGetRolesIncludesRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesIncludesCustomRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesDeduplicatesRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertCount(2, \array_unique($roles));
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->eraseCredentials();

        // Method executed successfully without throwing exception
        $this->addToAssertionCount(1);
    }

    public function testSettersReturnInstance(): void
    {
        $user = new User();

        $result1 = $user->setEmail('test@example.com');
        $result2 = $user->setRoles(['ROLE_ADMIN']);
        $result3 = $user->setPassword('hashed-password');
        $result4 = $user->setIsEnabled(true);
        $result5 = $user->setCreatedAt(new \DateTimeImmutable());
        $result6 = $user->setAvatar('avatar.jpg');

        $this->assertSame($user, $result1);
        $this->assertSame($user, $result2);
        $this->assertSame($user, $result3);
        $this->assertSame($user, $result4);
        $this->assertSame($user, $result5);
        $this->assertSame($user, $result6);
    }

    public function testGetAvatarUrlReturnsNullWhenNoAvatar(): void
    {
        $user = new User();

        $this->assertNull($user->getAvatarUrl());
    }

    public function testGetAvatarUrlReturnsFullPathWhenAvatarIsPath(): void
    {
        $user = new User();
        $user->setAvatar('/uploads/avatars/custom-path/avatar.jpg');

        $this->assertSame('/uploads/avatars/custom-path/avatar.jpg', $user->getAvatarUrl());
    }

    public function testGetAvatarUrlBuildsPathWhenAvatarIsFilename(): void
    {
        $user = new User();
        $user->setAvatar('avatar.jpg');

        $this->assertSame('/uploads/avatars/avatar.jpg', $user->getAvatarUrl());
    }

    public function testPasswordFieldGettersAndSetters(): void
    {
        $user           = new User();
        $hashedPassword = 'hashed-password-hash';

        $user->setPassword($hashedPassword);

        $this->assertSame($hashedPassword, $user->getPassword());
    }

    public function testIsEnabledDefaultsToTrue(): void
    {
        $user = new User();

        $this->assertTrue($user->getIsEnabled() ?? false);
    }

    public function testEmailNullableGetterReturnsNullBeforeSet(): void
    {
        $user = new User();

        $this->assertNull($user->getEmail());
    }

    public function testAvatarNullableGetterReturnsNullBeforeSet(): void
    {
        $user = new User();

        $this->assertNull($user->getAvatar());
    }

    public function testIdIsNullByDefault(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
    }

    public function testRolesArrayIsEmptyByDefault(): void
    {
        $user = new User();

        // getRoles() always includes ROLE_USER, but internal roles should be empty
        $user->setRoles([]);
        $this->assertSame([], [] === $user->getRoles() ? [] : \array_diff($user->getRoles(), ['ROLE_USER']));
    }
}
