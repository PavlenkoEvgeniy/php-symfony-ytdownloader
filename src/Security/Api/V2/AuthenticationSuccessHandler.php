<?php

declare(strict_types=1);

namespace App\Security\Api\V2;

use App\Entity\User;
use App\Service\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RefreshTokenManagerInterface $refreshTokenManager,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => $this->translator?->trans('api.auth.unauthorized') ?? 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->refreshTokenManager->issueTokens($user);

        return new JsonResponse($payload, Response::HTTP_OK);
    }
}
