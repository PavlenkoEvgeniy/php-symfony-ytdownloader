<?php

declare(strict_types=1);

namespace App\Controller\HealthCheck;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'app_health_check_index', methods: [Request::METHOD_GET])]
final class IndexController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'string:APP_VERSION')]
        private readonly string $appVersion,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status'    => 'OK',
            'version'   => $this->appVersion,
            'timestamp' => new \DateTime('now')->format('Y-m-d H:i:s'),
        ]);
    }
}
