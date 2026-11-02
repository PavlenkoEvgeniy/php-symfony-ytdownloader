<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Entity\Source;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Service\AuditLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/ui/source/download/{id}',
    name: 'ui_source_download',
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

    public function __invoke(Source $source): BinaryFileResponse|RedirectResponse
    {
        $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

        if (!\file_exists($filePath)) {
            $this->addFlash('error', $this->translator->trans('ui.source.error_download'));
            $this->logger->alert('An error occurred while downloading the file', [
                'message' => \sprintf('File not found, not possible to download file: %s', $filePath),
            ]);

            $this->auditLogger->log(
                action: AuditAction::SOURCE_DOWNLOAD,
                channel: AuditChannel::UI,
                targetType: 'source',
                targetId: $source->getId(),
                targetLabel: $source->getFilename(),
                success: false,
                metadata: ['reason' => 'file_not_found'],
            );

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->auditLogger->log(
            action: AuditAction::SOURCE_DOWNLOAD,
            channel: AuditChannel::UI,
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
