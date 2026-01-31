<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class RefreshTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly int $refreshTokenTtlSeconds,
    ) {
    }

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

    /**
     * @return array{token: string, refresh_token: string, refresh_token_expires_at: string}|null
     */
    public function refresh(string $tokenValue): ?array
    {
        $refreshToken = $this->refreshTokenRepository->findOneByToken($tokenValue);
        $now          = new \DateTimeImmutable();

        if (null === $refreshToken || $refreshToken->isRevoked() || $refreshToken->isExpired($now)) {
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
        $expiresAt = $now->modify('+' . $this->refreshTokenTtlSeconds . ' seconds');

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($user)
            ->setToken($this->generateTokenValue())
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt);

        return $refreshToken;
    }

    private function generateTokenValue(): string
    {
        return \bin2hex(\random_bytes(64));
    }
}
