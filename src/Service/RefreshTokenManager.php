<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RefreshTokenManager implements RefreshTokenManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private JWTTokenManagerInterface $jwtTokenManager,
        #[Autowire(env: 'int:JWT_REFRESH_TOKEN_TTL')]
        private int $refreshTokenTtlSeconds,
    ) {
    }

    #[\Override]
    /**
     * @return array{token: string, refresh_token: string, refresh_token_expires_at: string}
     */
    public function issueTokens(User $user): array
    {
        $refreshToken = $this->createRefreshToken($user);
        $jwtToken     = $this->jwtTokenManager->create($user);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return [
            'token'                    => $jwtToken,
            'refresh_token'            => (string) $refreshToken->getToken(),
            'refresh_token_expires_at' => $refreshToken->getExpiresAt()?->format(DATE_ATOM) ?? '',
        ];
    }

    #[\Override]
    /**
     * @return array{token: string, refresh_token: string, refresh_token_expires_at: string}|null
     */
    public function refresh(string $tokenValue): ?array
    {
        $refreshToken = $this->refreshTokenRepository->findOneByToken($tokenValue);
        $now          = new \DateTimeImmutable();

        if (!$refreshToken instanceof RefreshToken || $refreshToken->isRevoked() || $refreshToken->isExpired($now)) {
            return null;
        }

        $user = $refreshToken->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $refreshToken->revoke($now);
        $newRefreshToken = $this->createRefreshToken($user);
        $jwtToken        = $this->jwtTokenManager->create($user);

        $this->entityManager->persist($refreshToken);
        $this->entityManager->persist($newRefreshToken);
        $this->entityManager->flush();

        return [
            'token'                    => $jwtToken,
            'refresh_token'            => (string) $newRefreshToken->getToken(),
            'refresh_token_expires_at' => $newRefreshToken->getExpiresAt()?->format(DATE_ATOM) ?? '',
        ];
    }

    private function createRefreshToken(User $user): RefreshToken
    {
        $now       = new \DateTimeImmutable();
        $expiresAt = $now->modify(\sprintf('+%d seconds', $this->refreshTokenTtlSeconds));

        return new RefreshToken()->setUser($user)
            ->setToken($this->generateTokenValue())
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt)
        ;
    }

    private function generateTokenValue(): string
    {
        return \bin2hex(\random_bytes(64));
    }
}
