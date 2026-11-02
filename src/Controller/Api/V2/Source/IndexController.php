<?php

declare(strict_types=1);

namespace App\Controller\Api\V2\Source;

use App\RateLimiter\RateLimitAttribute;
use App\Repository\SourceRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(path: '/api/v2/source', name: 'api_v2_source_index', methods: [Request::METHOD_GET])]
final class IndexController extends AbstractController
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = \strtolower($request->query->getString('order', 'desc'));
        $order = \in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        $items = [];

        foreach ($this->sourceRepository->findAllOrderedByCreatedAt($order) as $source) {
            $items[] = [
                'id'           => $source->getId(),
                'filename'     => $source->getFilename(),
                'filepath'     => $source->getFilepath(),
                'size'         => $source->getSize(),
                'created_at'   => $source->getCreatedAt()?->format(DATE_ATOM),
                'download_url' => $this->generateUrl('api_v2_source_download', ['id' => $source->getId()]),
            ];
        }

        return $this->json([
            'items' => $items,
            'count' => \count($items),
        ]);
    }
}
