<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\RateLimiter\RateLimitAttribute;
use App\Service\DownloadDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[RateLimitAttribute(limit: 60, interval: 60)]
final class DownloadController extends AbstractController
{
    private const array ALLOWED_QUALITIES = ['best', 'moderate', 'poor', 'audio'];

    #[Route(path: '/api/v1/download/create', name: 'api_v1_download_create', methods: [Request::METHOD_POST])]
    public function create(Request $request, DownloadDispatcher $dispatcher): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $url     = isset($data['url']) ? \trim((string) $data['url']) : '';
        $quality = isset($data['quality']) ? \trim((string) $data['quality']) : 'moderate';

        if ('' === $url) {
            return $this->json(['message' => 'Url is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
            return $this->json(['message' => 'Url is not valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!\in_array($quality, self::ALLOWED_QUALITIES, true)) {
            return $this->json([
                'message' => 'Quality is not valid.',
                'allowed' => self::ALLOWED_QUALITIES,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dispatcher->dispatch($url, $quality);

        return $this->json([
            'message' => 'Download was added to queue.',
            'url'     => $url,
            'quality' => $quality,
        ], Response::HTTP_ACCEPTED);
    }
}
