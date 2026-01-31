<?php

declare(strict_types=1);

namespace App\Security\Api\V2;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly RefreshTokenManager $refreshTokenManager)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Unauthorized.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = $this->refreshTokenManager->issueTokens($user);

        return new JsonResponse($payload, JsonResponse::HTTP_OK);
    }
}
