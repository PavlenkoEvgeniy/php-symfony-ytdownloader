<?php

declare(strict_types=1);

namespace App\Controller\Api\V2\Auth;

use App\Dto\RefreshTokenRequest;
use App\RateLimiter\RateLimitAttribute;
use App\Service\RefreshTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(path: '/api/v2/auth/refresh', name: 'api_v2_auth_refresh', methods: [Request::METHOD_POST])]
final class RefreshController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        RefreshTokenRequest $payload,
    ): JsonResponse {
        $tokens = $this->refreshTokenManager->refresh($payload->refreshToken);

        if (null === $tokens) {
            return $this->json(['message' => $this->translator->trans('api.auth.invalid_refresh_token')], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($tokens);
    }
}
