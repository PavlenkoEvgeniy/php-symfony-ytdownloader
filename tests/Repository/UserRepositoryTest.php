<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private UserRepository $userRepository;

    public function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $entityManager */
        $entityManager                   = self::getContainer()->get('doctrine')->getManager();
        $this->entityManager             = $entityManager;
        $this->userRepository            = $this->entityManager->getRepository(User::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
            $this->entityManager->clear();
        }

        parent::tearDown();
    }

    /**
     * Verifies the total user count grows by the number of inserted users.
     */
    public function testGetTotalCountReflectsInsertedUsers(): void
    {
        $initial = $this->userRepository->getTotalCount();

        $u1 = new User();
        $u1->setEmail('a1@test.local');
        $u1->setPassword('pass');

        $u2 = new User();
        $u2->setEmail('a2@test.local');
        $u2->setPassword('pass');

        $this->entityManager->persist($u1);
        $this->entityManager->persist($u2);
        $this->entityManager->flush();

        $this->assertSame($initial + 2, $this->userRepository->getTotalCount());
    }

    /**
     * Verifies email lookup is case-insensitive and returns null for unknown users.
     */
    public function testFindOneByEmailReturnsUserOrNull(): void
    {
        $email = 'FindMe@test.local';

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('pass');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $found = $this->userRepository->findOneByEmail(\mb_strtolower($email));
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame(\mb_strtolower($email), $found->getEmail());

        $notFound = $this->userRepository->findOneByEmail('no-such-user@test.local');
        $this->assertNull($notFound);
    }

    /**
     * Verifies upgrading a password persists the new hash.
     */
    public function testUpgradePasswordPersistsNewHash(): void
    {
        $user = new User();
        $user->setEmail('upgrade@test.local');
        $user->setPassword('oldhash');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->userRepository->upgradePassword($user, 'newHash123');

        // clear and fetch again to ensure flush happened
        $this->entityManager->clear();
        $reloaded = $this->userRepository->findOneByEmail('upgrade@test.local');

        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('newHash123', $reloaded->getPassword());
    }

    /**
     * Verifies upgrading a password rejects users not managed by the repository.
     */
    public function testUpgradePasswordThrowsOnUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $fake = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): string
            {
                return 'x';
            }
        };

        $this->userRepository->upgradePassword($fake, 'whatever');
    }
}
