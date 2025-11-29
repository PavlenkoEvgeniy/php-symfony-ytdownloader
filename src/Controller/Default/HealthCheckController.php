<?php

declare(strict_types=1);

namespace App\Controller\Default;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HealthCheckController extends AbstractController
{
    #[Route('/health', name: 'app_health_check', methods: [Request::METHOD_GET])]
    public function check(string $appVersion): JsonResponse
    {
        $message = [
            'status'    => 'Ok',
            'version'   => $appVersion,
            'timestamp' => (new \DateTime('now'))->format('Y-m-d H:i:s'),
        ];

        return $this->json($message);
    }
}
