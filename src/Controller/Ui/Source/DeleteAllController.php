<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Repository\SourceRepositoryInterface;
use App\Service\AuditLoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/ui/source/delete-all', name: 'ui_source_delete_all', methods: [Request::METHOD_POST])]
final class DeleteAllController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $quantity = 0;
        $deleted  = 0;
        $failed   = 0;
        $bytes    = 0;

        if (!$this->isCsrfTokenValid('delete_all', $request->getPayload()->getString('_token'))) {
            $this->auditLogger->log(
                action: AuditAction::SOURCES_DELETE,
                channel: AuditChannel::UI,
                success: false,
                metadata: ['reason' => 'invalid_csrf'],
            );
        } else {
            $sources  = $this->sourceRepository->findAllSources();
            $quantity = \count($sources);

            if (0 === $quantity) {
                $this->logger->alert('An error occurred while deleting the files', [
                    'message' => 'Nothing to delete, quantity of files is zero',
                ]);
                $this->addFlash('error', $this->translator->trans('ui.source.error_nothing_to_delete'));

                $this->auditLogger->log(
                    action: AuditAction::SOURCES_DELETE,
                    channel: AuditChannel::UI,
                    success: false,
                    metadata: ['reason' => 'nothing_to_delete'],
                );

                return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($sources as $source) {
                $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

                if (!\file_exists($filePath)) {
                    $this->addFlash('error', $this->translator->trans('ui.source.error_delete_missing'));
                    $this->logger->alert('An error occurred while deleting the file', [
                        'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
                    ]);

                    $this->auditLogger->log(
                        action: AuditAction::SOURCES_DELETE,
                        channel: AuditChannel::UI,
                        success: false,
                        metadata: ['reason' => 'file_not_found', 'filename' => $source->getFilename()],
                    );

                    return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
                }

                $size = (int) \filesize($filePath);

                try {
                    \unlink($filePath);
                    ++$deleted;
                    $bytes += $size;
                } catch (\Throwable $e) {
                    ++$failed;
                    $this->addFlash('error', $this->translator->trans('ui.source.error_delete'));
                    $this->logger->alert('An error occurred while deleting the file', [
                        'message'  => $e->getMessage(),
                        'source'   => $source,
                        'filepath' => $filePath,
                    ]);

                    $this->auditLogger->log(
                        action: AuditAction::SOURCE_DELETE,
                        channel: AuditChannel::UI,
                        targetType: 'source',
                        targetId: $source->getId(),
                        targetLabel: $source->getFilename(),
                        success: false,
                        metadata: ['reason' => 'delete_failed', 'error' => $e->getMessage()],
                    );
                }

                $this->entityManager->remove($source);
            }

            $this->entityManager->flush();

            $this->auditLogger->log(
                action: AuditAction::SOURCES_DELETE,
                channel: AuditChannel::UI,
                success: true,
                metadata: ['deleted' => $deleted, 'failed' => $failed, 'bytes' => $bytes],
            );
        }

        if (1 === $quantity) {
            $this->addFlash('success', $this->translator->trans('ui.source.deleted_one'));
        } else {
            $this->addFlash('success', $this->translator->trans('ui.source.deleted_many', ['%count%' => $quantity]));
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }
}
