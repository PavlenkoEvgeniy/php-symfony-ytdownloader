<?php

declare(strict_types=1);

namespace App\Controller\HealthCheck;

use App\Redis\RedisConnectionFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health/ready', name: 'app_health_ready', methods: [Request::METHOD_GET])]
final class ReadyController extends AbstractController
{
    private const string STATUS_OK   = 'OK';
    private const string STATUS_FAIL = 'FAIL';

    public function __construct(
        private readonly Connection $connection,
        private readonly RedisConnectionFactory $redisConnectionFactory,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $checks = ['database' => self::STATUS_OK, 'redis' => self::STATUS_OK];
        $status = Response::HTTP_OK;

        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $checks['database'] = self::STATUS_FAIL;
            $status             = Response::HTTP_SERVICE_UNAVAILABLE;
        }

        try {
            $this->redisConnectionFactory->create()->ping();
        } catch (\Throwable) {
            $checks['redis'] = self::STATUS_FAIL;
            $status          = Response::HTTP_SERVICE_UNAVAILABLE;
        }

        return new JsonResponse([
            'status' => Response::HTTP_OK === $status ? self::STATUS_OK : self::STATUS_FAIL,
            'checks' => $checks,
        ], $status);
    }
}
