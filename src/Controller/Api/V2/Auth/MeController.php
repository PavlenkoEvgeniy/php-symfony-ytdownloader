<?php

declare(strict_types=1);

namespace App\Controller\Api\V2\Auth;

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
#[Route(path: '/api/v2/auth/me', name: 'api_v2_auth_me', methods: [Request::METHOD_GET])]
final class MeController extends AbstractController
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

        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
