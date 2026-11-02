<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Source;

use App\Entity\Source;
use App\RateLimiter\RateLimitAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(
    path: '/api/v1/source/{id}',
    name: 'api_v1_source_delete',
    requirements: ['id' => Requirement::DIGITS],
    methods: [Request::METHOD_DELETE],
)]
final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Source $source): JsonResponse
    {
        $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

        if (!\file_exists($filePath)) {
            $this->logger->alert('File not found for API delete', [
                'message'  => \sprintf('File not found, not possible to delete file: %s', $filePath),
                'sourceId' => $source->getId(),
            ]);

            return $this->json(['message' => $this->translator->trans('api.source.file_not_found')], Response::HTTP_NOT_FOUND);
        }

        try {
            \unlink($filePath);
        } catch (\Throwable $e) {
            $this->logger->alert('Error deleting file via API', [
                'message'  => $e->getMessage(),
                'sourceId' => $source->getId(),
                'filepath' => $filePath,
            ]);

            return $this->json(['message' => $this->translator->trans('api.source.delete_failed')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->entityManager->remove($source);
        $this->entityManager->flush();

        return $this->json(['message' => $this->translator->trans('api.source.deleted')], Response::HTTP_OK);
    }
}
