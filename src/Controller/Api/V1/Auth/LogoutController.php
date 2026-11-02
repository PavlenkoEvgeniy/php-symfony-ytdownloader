<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Auth;

use App\Entity\User;
use App\RateLimiter\RateLimitAttribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(path: '/api/v1/auth/logout', name: 'api_v1_auth_logout', methods: [Request::METHOD_POST])]
final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        ?User $user,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['message' => $this->translator->trans('api.auth.unauthorized')], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['message' => $this->translator->trans('api.auth.logged_out')], Response::HTTP_OK);
    }
}
