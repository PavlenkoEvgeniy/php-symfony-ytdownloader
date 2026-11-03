<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Download;

use App\Dto\DownloadRequest;
use App\Entity\DownloadTask;
use App\Entity\User;
use App\RateLimiter\RateLimitAttribute;
use App\Service\DownloadDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(path: '/api/v1/download/create', name: 'api_v1_download_create', methods: [Request::METHOD_POST])]
final class CreateController extends AbstractController
{
    public function __construct(
        private readonly DownloadDispatcherInterface $downloadDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        DownloadRequest $payload,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $this->downloadDispatcher->dispatch($payload->url, $payload->quality, $user, DownloadTask::ORIGIN_API);

        return $this->json([
            'message' => $this->translator->trans('api.download.queued'),
            'url'     => $payload->url,
            'quality' => $payload->quality,
        ], Response::HTTP_ACCEPTED);
    }
}
