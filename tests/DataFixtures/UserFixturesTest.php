<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixturesTest extends KernelTestCase
{
    private UserFixtures $fixtures;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->fixtures       = new UserFixtures($this->passwordHasher);
    }

    /**
     * Verifies the fixtures expose the user and all groups.
     */
    public function testFixturesHasCorrectGroups(): void
    {
        $groups = UserFixtures::getGroups();

        $this->assertContains('user', $groups);
        $this->assertContains('all', $groups);
        $this->assertCount(2, $groups);
    }

    /**
     * Verifies the fixtures implement the fixture group interface.
     */
    public function testFixturesImplementsCorrectInterface(): void
    {
        $interfaces = \class_implements(\get_class($this->fixtures));
        $this->assertArrayHasKey('Doctrine\\Bundle\\FixturesBundle\\FixtureGroupInterface', (array) $interfaces);
    }

    /**
     * Verifies loading the fixtures creates at least four users.
     */
    public function testFixturesCanLoadData(): void
    {
        $entityManager             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository            = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(User::class);

        foreach ($userRepository->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();

        $this->fixtures->load($entityManager);

        $allUsers = $userRepository->findAll();
        $this->assertGreaterThanOrEqual(4, \count($allUsers));
    }

    /**
     * Verifies the fixtures create an enabled admin user with the admin role.
     */
    public function testFixturesCreateAdminUser(): void
    {
        $entityManager             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository            = $entityManager->getRepository(User::class);

        foreach ($userRepository->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();

        $this->fixtures->load($entityManager);

        $adminUser = $userRepository->findOneByEmail('admin@admin.local');
        $this->assertInstanceOf(User::class, $adminUser);
        $this->assertContains('ROLE_ADMIN', $adminUser->getRoles());
        $this->assertTrue($adminUser->getIsEnabled() ?? false);
    }

    /**
     * Verifies the fixtures create an enabled regular user.
     */
    public function testFixturesCreateRegularUser(): void
    {
        $entityManager             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository            = $entityManager->getRepository(User::class);

        foreach ($userRepository->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();

        $this->fixtures->load($entityManager);

        $user = $userRepository->findOneByEmail('user@test.local');
        $this->assertInstanceOf(User::class, $user);
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertTrue($user->getIsEnabled() ?? false);
    }

    /**
     * Verifies the fixtures create disabled admin and regular users.
     */
    public function testFixturesCreateDisabledUsers(): void
    {
        $entityManager             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository            = $entityManager->getRepository(User::class);

        foreach ($userRepository->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();

        $this->fixtures->load($entityManager);

        $disabledAdmin = $userRepository->findOneByEmail('admin.disabled@admin.local');
        $disabledUser  = $userRepository->findOneByEmail('user.disabled@test.local');

        $this->assertInstanceOf(User::class, $disabledAdmin);
        $this->assertInstanceOf(User::class, $disabledUser);
        $this->assertFalse($disabledAdmin->getIsEnabled() ?? false);
        $this->assertFalse($disabledUser->getIsEnabled() ?? false);
    }

    /**
     * Verifies fixture passwords are stored hashed rather than in plain text.
     */
    public function testFixturesPasswordsAreHashed(): void
    {
        $entityManager             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository            = $entityManager->getRepository(User::class);

        foreach ($userRepository->findAll() as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();

        $this->fixtures->load($entityManager);

        $adminUser = $userRepository->findOneByEmail('admin@admin.local');
        $this->assertNotSame('admin123456', $adminUser?->getPassword());
    }
}
