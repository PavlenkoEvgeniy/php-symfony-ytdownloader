<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RefreshTokenRepositoryTest extends KernelTestCase
{
    private EntityManager $em;
    private RefreshTokenRepository $refreshTokenRepository;

    public function setUp(): void
    {
        // Skip functional DB tests if the database host is not resolvable in this environment
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? \getenv('DATABASE_URL') ?: '';
        $parts = \parse_url($dbUrl);
        if (false !== $parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (\gethostbyname($host) === $host) {
                $this->markTestSkipped('Database host not resolvable - skipping RefreshTokenRepository tests.');
            }
        }

        self::bootKernel();

        /** @var EntityManager $em */
        $em                           = self::getContainer()->get('doctrine')->getManager();
        $this->em                     = $em;
        $this->refreshTokenRepository = $this->em->getRepository(RefreshToken::class);

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

    public function testFindOneByTokenReturnsEntityOrNull(): void
    {
        $user = new User();
        $user->setEmail('refresh@example.test');
        $user->setPassword('pass');

        $this->em->persist($user);
        $this->em->flush();

        $tokenValue = 'token-value-123';

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($user)
            ->setToken($tokenValue)
            ->setCreatedAt(new \DateTimeImmutable('now'))
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->em->persist($refreshToken);
        $this->em->flush();

        $found = $this->refreshTokenRepository->findOneByToken($tokenValue);
        $this->assertInstanceOf(RefreshToken::class, $found);
        $this->assertSame($tokenValue, $found->getToken());
        $this->assertSame($user->getId(), $found->getUser()?->getId());

        $notFound = $this->refreshTokenRepository->findOneByToken('missing-token');
        $this->assertNull($notFound);
    }
}
