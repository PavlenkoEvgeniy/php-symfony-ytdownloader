<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;

final class RefreshTokenManagerTest extends TestCase
{
    private RefreshTokenManager $manager;
    private EntityManagerInterface $entityManager;
    private RefreshTokenRepository $repository;
    private JWTTokenManagerInterface $jwtTokenManager;
    private int $refreshTokenTtlSeconds = 3600; // 1 hour

    protected function setUp(): void
    {
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        /** @phpstan-ignore-next-line */
        $this->repository      = $this->createMock(RefreshTokenRepository::class);
        $this->jwtTokenManager = $this->createMock(JWTTokenManagerInterface::class);

        $this->manager = new RefreshTokenManager(
            $this->entityManager,
            $this->repository,
            $this->jwtTokenManager,
            $this->refreshTokenTtlSeconds,
        );
    }

    public function testIssueTokensCreatesRefreshToken(): void
    {
        $user = $this->createUser();

        /** @phpstan-ignore-next-line */
        $this->jwtTokenManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('jwt-token-value');

        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('persist');
        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->manager->issueTokens($user);

        $this->assertSame('jwt-token-value', $result['token']);
    }

    public function testIssueTokensRefreshTokenExpiresAtCorrectTime(): void
    {
        $user = $this->createUser();

        /** @phpstan-ignore-next-line */
        $this->jwtTokenManager->expects($this->once())
            ->method('create')
            ->willReturn('jwt-token');

        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('persist');
        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->manager->issueTokens($user);

        $expiresAt      = new \DateTimeImmutable($result['refresh_token_expires_at']);
        $now            = new \DateTimeImmutable();
        $expectedExpiry = $now->modify('+' . $this->refreshTokenTtlSeconds . ' seconds');

        // Allow 1 second tolerance for test execution
        $this->assertLessThanOrEqual(1, \abs($expiresAt->getTimestamp() - $expectedExpiry->getTimestamp()));
    }

    public function testRefreshReturnsNullIfTokenNotFound(): void
    {
        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->with('non-existent-token')
            ->willReturn(null);

        $result = $this->manager->refresh('non-existent-token');

        $this->assertNull($result);
    }

    public function testRefreshReturnsNullIfTokenIsRevoked(): void
    {
        $token = $this->createMockToken();
        $token->expects($this->once())
            ->method('isRevoked')
            ->willReturn(true);

        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->willReturn($token);

        $result = $this->manager->refresh('revoked-token');

        $this->assertNull($result);
    }

    public function testRefreshReturnsNullIfTokenIsExpired(): void
    {
        $token = $this->createMockToken();
        $token->expects($this->once())
            ->method('isRevoked')
            ->willReturn(false);
        $token->expects($this->once())
            ->method('isExpired')
            ->with($this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(true);

        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->willReturn($token);

        $result = $this->manager->refresh('expired-token');

        $this->assertNull($result);
    }

    public function testRefreshReturnsNullIfUserNotFound(): void
    {
        $token = $this->createMockToken();
        $token->expects($this->once())
            ->method('isRevoked')
            ->willReturn(false);
        $token->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);
        $token->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->willReturn($token);

        $result = $this->manager->refresh('valid-token');

        $this->assertNull($result);
    }

    public function testRefreshRevokesOldTokenAndCreatesNew(): void
    {
        $user     = $this->createUser();
        $oldToken = new RefreshToken();
        $oldToken->setUser($user);
        $oldToken->setToken('old-token-value');
        $oldToken->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $oldToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->with('old-token-value')
            ->willReturn($oldToken);

        /** @phpstan-ignore-next-line */
        $this->jwtTokenManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('new-jwt-token');

        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->exactly(2))
            ->method('persist');
        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->manager->refresh('old-token-value');

        $this->assertSame('new-jwt-token', $result['token']);
        $this->assertNotSame('old-token-value', $result['refresh_token']);
        $this->assertTrue($oldToken->isRevoked());
    }

    public function testRefreshReturnsValidTokenStructure(): void
    {
        $user     = $this->createUser();
        $oldToken = new RefreshToken();
        $oldToken->setUser($user);
        $oldToken->setToken('old-token');
        $oldToken->setCreatedAt(new \DateTimeImmutable());
        $oldToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        /** @phpstan-ignore-next-line */
        $this->repository->expects($this->once())
            ->method('findOneByToken')
            ->willReturn($oldToken);

        /** @phpstan-ignore-next-line */
        $this->jwtTokenManager->expects($this->once())
            ->method('create')
            ->willReturn('jwt-token');

        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->exactly(2))
            ->method('persist');
        /** @phpstan-ignore-next-line */
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->manager->refresh('old-token');
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed-password');

        return $user;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject&RefreshToken
     */
    private function createMockToken(): mixed
    {
        return $this->createMock(RefreshToken::class);
    }
}
