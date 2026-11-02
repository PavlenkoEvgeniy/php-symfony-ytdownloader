<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Service\QueueStatsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ui/source/queue-stats', name: 'ui_source_queue_stats', methods: [Request::METHOD_GET])]
final class QueueStatsController extends AbstractController
{
    public function __construct(
        private readonly QueueStatsServiceInterface $queueStatsService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->queueStatsService->getStats());
    }
}
