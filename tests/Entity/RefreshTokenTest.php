<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\RefreshToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    /**
     * Verifies a token without a revocation time is not revoked.
     */
    public function testIsRevokedReturnsFalseWhenNotRevoked(): void
    {
        $token = new RefreshToken();
        $token->setRevokedAt(null);

        $this->assertFalse($token->isRevoked());
    }

    /**
     * Verifies a token with a revocation time is revoked.
     */
    public function testIsRevokedReturnsTrueWhenRevoked(): void
    {
        $token = new RefreshToken();
        $token->setRevokedAt(new \DateTimeImmutable());

        $this->assertTrue($token->isRevoked());
    }

    /**
     * Verifies a token with a future expiry is not expired.
     */
    public function testIsExpiredReturnsFalseWhenNotExpired(): void
    {
        $token        = new RefreshToken();
        $now          = new \DateTimeImmutable();
        $futureExpiry = $now->modify('+1 hour');
        $token->setExpiresAt($futureExpiry);

        $this->assertFalse($token->isExpired($now));
    }

    /**
     * Verifies a token with a past expiry is expired.
     */
    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $token      = new RefreshToken();
        $now        = new \DateTimeImmutable();
        $pastExpiry = $now->modify('-1 hour');
        $token->setExpiresAt($pastExpiry);

        $this->assertTrue($token->isExpired($now));
    }

    /**
     * Verifies a token is expired exactly at its expiry time.
     */
    public function testIsExpiredReturnsTrueWhenExpiryIsEqualToNow(): void
    {
        $token = new RefreshToken();
        $now   = new \DateTimeImmutable();
        $token->setExpiresAt($now);

        $this->assertTrue($token->isExpired($now));
    }

    /**
     * Verifies a token without an expiry never expires.
     */
    public function testIsExpiredReturnsFalseWhenExpiryIsNull(): void
    {
        $token = new RefreshToken();
        $now   = new \DateTimeImmutable();

        $this->assertFalse($token->isExpired($now));
    }

    /**
     * Verifies revoking a token stores the given time and returns the token.
     */
    public function testRevokeSetRevokedAtTime(): void
    {
        $token = new RefreshToken();
        $now   = new \DateTimeImmutable();

        $result = $token->revoke($now);

        $this->assertSame($now, $token->getRevokedAt());
        $this->assertSame($token, $result);
    }

    /**
     * Verifies revoking a token returns the same instance.
     */
    public function testRevokeReturnsTokenInstance(): void
    {
        $token = new RefreshToken();
        $now   = new \DateTimeImmutable();

        $result = $token->revoke($now);

        $this->assertSame($token, $result);
    }

    /**
     * Verifies every refresh token setter returns the same instance.
     */
    public function testTokenSettersReturnInstance(): void
    {
        $token = new RefreshToken();
        $user  = new User();
        $now   = new \DateTimeImmutable();

        $result1 = $token->setToken('test-token-value');
        $result2 = $token->setUser($user);
        $result3 = $token->setCreatedAt($now);
        $result4 = $token->setExpiresAt($now);
        $result5 = $token->setRevokedAt($now);

        $this->assertSame($token, $result1);
        $this->assertSame($token, $result2);
        $this->assertSame($token, $result3);
        $this->assertSame($token, $result4);
        $this->assertSame($token, $result5);
    }

    /**
     * Verifies refresh token getters return the assigned values.
     */
    public function testTokenGettersReturnSetValues(): void
    {
        $token      = new RefreshToken();
        $user       = new User();
        $tokenValue = 'test-token-value';
        $createdAt  = new \DateTimeImmutable();
        $expiresAt  = new \DateTimeImmutable('+1 hour');
        $revokedAt  = new \DateTimeImmutable();

        $token->setToken($tokenValue);
        $token->setUser($user);
        $token->setCreatedAt($createdAt);
        $token->setExpiresAt($expiresAt);
        $token->setRevokedAt($revokedAt);

        $this->assertSame($tokenValue, $token->getToken());
        $this->assertSame($user, $token->getUser());
        $this->assertSame($createdAt, $token->getCreatedAt());
        $this->assertSame($expiresAt, $token->getExpiresAt());
        $this->assertSame($revokedAt, $token->getRevokedAt());
    }

    /**
     * Verifies a new refresh token has no identifier before persistence.
     */
    public function testTokenIdIsNullByDefault(): void
    {
        $token = new RefreshToken();

        $this->assertNull($token->getId());
    }

    /**
     * Verifies all refresh token values are null before being set.
     */
    public function testTokenValuesAreNullByDefault(): void
    {
        $token = new RefreshToken();

        $this->assertNull($token->getToken());
        $this->assertNull($token->getUser());
        $this->assertNull($token->getCreatedAt());
        $this->assertNull($token->getExpiresAt());
        $this->assertNull($token->getRevokedAt());
    }
}
