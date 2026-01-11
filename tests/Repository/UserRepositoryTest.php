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
    private EntityManager $em;
    private UserRepository $userRepository;

    public function setUp(): void
    {
        // Skip functional DB tests if the database host is not resolvable in this environment
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? \getenv('DATABASE_URL') ?: '';
        $parts = \parse_url($dbUrl);
        if (false !== $parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (\gethostbyname($host) === $host) {
                $this->markTestSkipped('Database host not resolvable - skipping UserRepository tests.');
            }
        }

        self::bootKernel();

        /** @var EntityManager $em */
        $em                   = self::getContainer()->get('doctrine')->getManager();
        $this->em             = $em;
        $this->userRepository = $this->em->getRepository(User::class);

        $this->em->getConnection()->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
            $this->em->clear();
        }

        parent::tearDown();
    }

    public function testGetTotalCountReflectsInsertedUsers(): void
    {
        $initial = $this->userRepository->getTotalCount();

        $u1 = new User();
        $u1->setEmail('a1@example.test');
        $u1->setPassword('pass');

        $u2 = new User();
        $u2->setEmail('a2@example.test');
        $u2->setPassword('pass');

        $this->em->persist($u1);
        $this->em->persist($u2);
        $this->em->flush();

        $this->assertSame($initial + 2, $this->userRepository->getTotalCount());
    }

    public function testFindOneByEmailReturnsUserOrNull(): void
    {
        $email = 'FindMe@example.test';

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('pass');

        $this->em->persist($user);
        $this->em->flush();

        $found = $this->userRepository->findOneByEmail(\mb_strtolower($email));
        $this->assertInstanceOf(User::class, $found);
        $this->assertSame(\mb_strtolower($email), $found->getEmail());

        $notFound = $this->userRepository->findOneByEmail('no-such-user@example.test');
        $this->assertNull($notFound);
    }

    public function testUpgradePasswordPersistsNewHash(): void
    {
        $user = new User();
        $user->setEmail('upgrade@example.test');
        $user->setPassword('oldhash');

        $this->em->persist($user);
        $this->em->flush();

        $this->userRepository->upgradePassword($user, 'newHash123');

        // clear and fetch again to ensure flush happened
        $this->em->clear();
        $reloaded = $this->userRepository->findOneByEmail('upgrade@example.test');

        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('newHash123', $reloaded->getPassword());
    }

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
