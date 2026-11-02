<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Exception\PropertyIsNotSetException;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    /**
     * Verifies a new user receives a creation timestamp.
     */
    public function testUserConstructorInitializesCreatedAt(): void
    {
        $user = new User();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    /**
     * Verifies two generated passwords differ.
     */
    public function testGeneratePasswordReturnsRandomString(): void
    {
        $password1 = User::generatePassword();
        $password2 = User::generatePassword();

        $this->assertNotSame($password1, $password2);
    }

    /**
     * Verifies generated passwords use the default length.
     */
    public function testGeneratePasswordReturnsDefaultLength(): void
    {
        $password = User::generatePassword();

        $this->assertSame(User::DEFAULT_PASSWORD_LENGTH, \strlen($password));
    }

    /**
     * Verifies generated passwords honor a custom length.
     */
    public function testGeneratePasswordReturnsCustomLength(): void
    {
        $password = User::generatePassword(10);

        $this->assertSame(10, \strlen($password));
    }

    /**
     * Verifies generated passwords contain only letters and digits.
     */
    public function testGeneratePasswordContainsOnlyAlphanumeric(): void
    {
        $password = User::generatePassword(100);

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $password);
    }

    /**
     * Verifies setting an email normalizes it to lowercase.
     */
    public function testEmailIsNormalizedToLowercase(): void
    {
        $user = new User();
        $user->setEmail('Admin@TEST.LOCAL');

        $this->assertSame('admin@test.local', $user->getEmail());
    }

    /**
     * Verifies the security identifier is the user email.
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('test@test.local');

        $this->assertSame('test@test.local', $user->getUserIdentifier());
    }

    /**
     * Verifies every user implicitly has the user role.
     */
    public function testGetRolesIncludesRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    /**
     * Verifies custom roles are returned alongside the user role.
     */
    public function testGetRolesIncludesCustomRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    /**
     * Verifies getRoles does not return duplicate roles.
     */
    public function testGetRolesDeduplicatesRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertCount(2, \array_unique($roles));
    }

    /**
     * Verifies every user setter returns the same instance.
     */
    public function testSettersReturnInstance(): void
    {
        $user = new User();

        $result1 = $user->setEmail('test@test.local');
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

    /**
     * Verifies the avatar URL is null when no avatar is set.
     */
    public function testGetAvatarUrlReturnsNullWhenNoAvatar(): void
    {
        $user = new User();

        $this->assertNull($user->getAvatarUrl());
    }

    /**
     * Verifies an absolute avatar path is returned unchanged.
     */
    public function testGetAvatarUrlReturnsFullPathWhenAvatarIsPath(): void
    {
        $user = new User();
        $user->setAvatar('/uploads/avatars/custom-path/avatar.jpg');

        $this->assertSame('/uploads/avatars/custom-path/avatar.jpg', $user->getAvatarUrl());
    }

    /**
     * Verifies a bare avatar filename is prefixed with the upload path.
     */
    public function testGetAvatarUrlBuildsPathWhenAvatarIsFilename(): void
    {
        $user = new User();
        $user->setAvatar('avatar.jpg');

        $this->assertSame('/uploads/avatars/avatar.jpg', $user->getAvatarUrl());
    }

    /**
     * Verifies the password hash is stored and returned unchanged.
     */
    public function testPasswordFieldGettersAndSetters(): void
    {
        $user           = new User();
        $hashedPassword = 'hashed-password-hash';

        $user->setPassword($hashedPassword);

        $this->assertSame($hashedPassword, $user->getPassword());
    }

    /**
     * Verifies a new user is enabled by default.
     */
    public function testIsEnabledDefaultsToTrue(): void
    {
        $user = new User();

        $this->assertTrue($user->getIsEnabled() ?? false);
    }

    /**
     * Verifies the email getter returns null before an email is set.
     */
    public function testEmailNullableGetterReturnsNullBeforeSet(): void
    {
        $user = new User();

        $this->assertNull($user->getEmail());
    }

    /**
     * Verifies the avatar getter returns null before an avatar is set.
     */
    public function testAvatarNullableGetterReturnsNullBeforeSet(): void
    {
        $user = new User();

        $this->assertNull($user->getAvatar());
    }

    /**
     * Verifies a new user has no identifier before persistence.
     */
    public function testIdIsNullByDefault(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
    }

    /**
     * Verifies a user without explicit roles gets only the implicit user role.
     */
    public function testDefaultRolesContainOnlyUserRole(): void
    {
        $user = new User();
        $user->setRoles([]);

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    /**
     * Verifies the "or fail" getters return assigned values.
     */
    public function testOrFailGettersReturnAssignedValues(): void
    {
        $user = new User();

        $user->setEmail('or-fail@test.local')
            ->setPassword('secret')
            ->setIsEnabled(true)
        ;

        $this->assertSame('or-fail@test.local', $user->getEmailOrFail());
        $this->assertSame('secret', $user->getPasswordOrFail());
        $this->assertTrue($user->getIsEnabledOrFail());
        $this->assertSame($user->getCreatedAt(), $user->getCreatedAtOrFail());
    }

    /**
     * Verifies the "or fail" getters throw when a value is not set.
     */
    public function testOrFailGettersThrowForUnsetValues(): void
    {
        $user = new User();

        $user->setCreatedAt(null);

        $this->expectException(PropertyIsNotSetException::class);
        $this->expectExceptionMessage('Property "createdAt" is not set.');

        $user->getCreatedAtOrFail();
    }

    /**
     * Verifies the identifier "or fail" getter throws before persistence.
     */
    public function testIdOrFailThrowsForUnsavedUser(): void
    {
        $this->expectException(PropertyIsNotSetException::class);

        new User()->getIdOrFail();
    }
}
