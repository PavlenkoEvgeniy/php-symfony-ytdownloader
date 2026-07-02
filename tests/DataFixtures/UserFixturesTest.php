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

    public function testFixturesHasCorrectGroups(): void
    {
        $groups = UserFixtures::getGroups();

        $this->assertContains('user', $groups);
        $this->assertContains('all', $groups);
        $this->assertCount(2, $groups);
    }

    public function testFixturesCanBeInstantiated(): void
    {
        // Fixture is instantiated in setUp()
        $this->addToAssertionCount(1);
    }

    public function testFixturesImplementsCorrectInterface(): void
    {
        // Verify it implements the required interfaces
        $interfaces = \class_implements(\get_class($this->fixtures));
        $this->assertArrayHasKey('Doctrine\\Bundle\\FixturesBundle\\FixtureGroupInterface', (array) $interfaces);
    }

    public function testFixturesCanLoadData(): void
    {
        $em             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(User::class);

        // Clear existing fixtures
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        // Load fixtures
        $this->fixtures->load($em);

        // Verify users were created
        $allUsers = $userRepository->findAll();
        $this->assertGreaterThanOrEqual(4, \count($allUsers));
    }

    public function testFixturesCreateAdminUser(): void
    {
        $em             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Clear existing fixtures
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        // Load fixtures
        $this->fixtures->load($em);

        // Find admin user
        $adminUser = $userRepository->findOneByEmail('admin@admin.local');
        $this->assertInstanceOf(User::class, $adminUser);
        $this->assertContains('ROLE_ADMIN', $adminUser->getRoles());
        $this->assertTrue($adminUser->getIsEnabled() ?? false);
    }

    public function testFixturesCreateRegularUser(): void
    {
        $em             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Clear existing fixtures
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        // Load fixtures
        $this->fixtures->load($em);

        // Find regular user
        $user = $userRepository->findOneByEmail('user@test.local');
        $this->assertInstanceOf(User::class, $user);
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertTrue($user->getIsEnabled() ?? false);
    }

    public function testFixturesCreateDisabledUsers(): void
    {
        $em             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Clear existing fixtures
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        // Load fixtures
        $this->fixtures->load($em);

        // Find disabled users
        $disabledAdmin = $userRepository->findOneByEmail('admin.disabled@admin.local');
        $disabledUser  = $userRepository->findOneByEmail('user.disabled@test.local');

        $this->assertInstanceOf(User::class, $disabledAdmin);
        $this->assertInstanceOf(User::class, $disabledUser);
        $this->assertFalse($disabledAdmin->getIsEnabled() ?? false);
        $this->assertFalse($disabledUser->getIsEnabled() ?? false);
    }

    public function testFixturesPasswordsAreHashed(): void
    {
        $em             = self::getContainer()->get('doctrine.orm.entity_manager');
        $userRepository = $em->getRepository(User::class);

        // Clear existing fixtures
        foreach ($userRepository->findAll() as $user) {
            $em->remove($user);
        }
        $em->flush();

        // Load fixtures
        $this->fixtures->load($em);

        // Get a user and verify password is hashed
        $adminUser = $userRepository->findOneByEmail('admin@admin.local');
        $this->assertNotSame('admin123456', $adminUser?->getPassword());
    }
}
