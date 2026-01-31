<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Message\DownloadMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadController extends AbstractController
{
    private const ALLOWED_QUALITIES = ['best', 'moderate', 'poor', 'audio'];

    /**
     * @throws ExceptionInterface
     */
    #[Route(path: '/api/v1/download/create', name: 'api_v1_download_create', methods: [Request::METHOD_POST])]
    public function create(Request $request, MessageBusInterface $bus): JsonResponse
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

        $bus->dispatch(new DownloadMessage($url, $quality));

        return $this->json([
            'message' => 'Download was added to queue.',
            'url'     => $url,
            'quality' => $quality,
        ], Response::HTTP_ACCEPTED);
    }
}
