<?php

declare(strict_types=1);

namespace App\Controller\Api\V2;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route(path: '/api/v2/auth/login', name: 'api_v2_auth_login', methods: [Request::METHOD_POST])]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Login request handled by security firewall.']);
    }

    #[Route(path: '/api/v2/auth/me', name: 'api_v2_auth_me', methods: [Request::METHOD_GET])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route(path: '/api/v2/auth/refresh', name: 'api_v2_auth_refresh', methods: [Request::METHOD_POST])]
    public function refresh(Request $request, RefreshTokenManager $refreshTokenManager): JsonResponse
    {
        $payload      = $request->toArray();
        $refreshToken = $payload['refresh_token'] ?? null;

        if (!\is_string($refreshToken) || '' === \trim($refreshToken)) {
            return $this->json(['message' => 'Refresh token is required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $tokens = $refreshTokenManager->refresh($refreshToken);

        if (null === $tokens) {
            return $this->json(['message' => 'Invalid refresh token.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json($tokens);
    }

    #[Route(path: '/api/v2/auth/logout', name: 'api_v2_auth_logout', methods: [Request::METHOD_POST])]
    public function logout(): JsonResponse
    {
        if (!$this->getUser() instanceof User) {
            return $this->json(['message' => 'Unauthorized.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json(['message' => 'Logged out.'], JsonResponse::HTTP_OK);
    }
}
