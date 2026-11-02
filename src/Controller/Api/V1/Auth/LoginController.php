<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Auth;

use App\RateLimiter\RateLimitAttribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(path: '/api/v1/auth/login', name: 'api_v1_auth_login', methods: [Request::METHOD_POST])]
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json(['message' => $this->translator->trans('api.auth.login_handled')]);
    }
}
