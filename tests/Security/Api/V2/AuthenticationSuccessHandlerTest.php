<?php

declare(strict_types=1);

namespace App\Tests\Security\Api\V2;

use App\Entity\User;
use App\Security\Api\V2\AuthenticationSuccessHandler;
use App\Service\RefreshTokenManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class AuthenticationSuccessHandlerTest extends TestCase
{
    public function testReturnsUnauthorizedWhenUserIsInvalid(): void
    {
        $refreshTokenManager = $this->createMock(RefreshTokenManager::class);
        $refreshTokenManager
            ->expects($this->never())
            ->method('issueTokens');

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $handler  = new AuthenticationSuccessHandler($refreshTokenManager);
        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        $this->assertSame(401, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Unauthorized.', $data['message'] ?? null);
    }

    public function testReturnsTokensForValidUser(): void
    {
        $user = new User();

        $expectedPayload = [
            'token'                    => 'jwt-token',
            'refresh_token'            => 'refresh-token',
            'refresh_token_expires_at' => '2026-01-31T12:00:00+00:00',
        ];

        $refreshTokenManager = $this->createMock(RefreshTokenManager::class);
        $refreshTokenManager
            ->expects($this->once())
            ->method('issueTokens')
            ->with($user)
            ->willReturn($expectedPayload);

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $handler  = new AuthenticationSuccessHandler($refreshTokenManager);
        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        $this->assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($expectedPayload, $data);
    }
}
