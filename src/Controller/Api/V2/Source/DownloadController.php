<?php

declare(strict_types=1);

namespace App\Controller\Api\V2\Source;

use App\Entity\Source;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\RateLimiter\RateLimitAttribute;
use App\Service\AuditLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RateLimitAttribute(interval: 60, limit: 60)]
#[Route(
    path: '/api/v2/source/{id}/download',
    name: 'api_v2_source_download',
    requirements: ['id' => Requirement::DIGITS],
    methods: [Request::METHOD_GET],
)]
final class DownloadController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(Source $source): Response
    {
        $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

        if (!\file_exists($filePath)) {
            $this->logger->alert('File not found for API download', [
                'message'  => \sprintf('File not found, not possible to download file: %s', $filePath),
                'sourceId' => $source->getId(),
            ]);

            $this->auditLogger->log(
                action: AuditAction::SOURCE_DOWNLOAD,
                channel: AuditChannel::API_V2,
                targetType: 'source',
                targetId: $source->getId(),
                targetLabel: $source->getFilename(),
                success: false,
                metadata: ['reason' => 'file_not_found'],
            );

            return $this->json(['message' => $this->translator->trans('api.source.file_not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->auditLogger->log(
            action: AuditAction::SOURCE_DOWNLOAD,
            channel: AuditChannel::API_V2,
            targetType: 'source',
            targetId: $source->getId(),
            targetLabel: $source->getFilename(),
        );

        return new BinaryFileResponse($filePath)->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $source->getFilename(),
        );
    }
}
