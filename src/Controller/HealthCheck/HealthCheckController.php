<?php

declare(strict_types=1);

namespace App\Controller\HealthCheck;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HealthCheckController extends AbstractController
{
    #[Route('/health', name: 'app_health_check_index', methods: [Request::METHOD_GET])]
    public function index(string $appVersion): JsonResponse
    {
        $message = [
            'status'    => 'OK',
            'version'   => $appVersion,
            'timestamp' => (new \DateTime('now'))->format('Y-m-d H:i:s'),
        ];

        return $this->json($message);
    }
}
