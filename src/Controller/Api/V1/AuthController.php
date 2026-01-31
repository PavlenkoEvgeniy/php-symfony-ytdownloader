<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route(path: '/api/v1/auth/login', name: 'api_v1_auth_login', methods: [Request::METHOD_POST])]
    public function login(): JsonResponse
    {
        return $this->json(['message' => 'Login request handled by security firewall.']);
    }

    #[Route(path: '/api/v1/auth/me', name: 'api_v1_auth_me', methods: [Request::METHOD_GET])]
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

    #[Route(path: '/api/v1/auth/logout', name: 'api_v1_auth_logout', methods: [Request::METHOD_POST])]
    public function logout(): JsonResponse
    {
        if (!$this->getUser() instanceof User) {
            return $this->json(['message' => 'Unauthorized.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json(['message' => 'Logged out.'], JsonResponse::HTTP_OK);
    }
}
