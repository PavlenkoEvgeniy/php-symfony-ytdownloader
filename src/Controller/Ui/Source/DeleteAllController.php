<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Repository\SourceRepositoryInterface;
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
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $quantity = 0;

        if ($this->isCsrfTokenValid('delete_all', $request->getPayload()->getString('_token'))) {
            $sources  = $this->sourceRepository->findAllSources();
            $quantity = \count($sources);

            if (0 === $quantity) {
                $this->logger->alert('An error occurred while deleting the files', [
                    'message' => 'Nothing to delete, quantity of files is zero',
                ]);
                $this->addFlash('error', $this->translator->trans('ui.source.error_nothing_to_delete'));

                return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
            }

            foreach ($sources as $source) {
                $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

                if (!\file_exists($filePath)) {
                    $this->addFlash('error', $this->translator->trans('ui.source.error_delete_missing'));
                    $this->logger->alert('An error occurred while deleting the file', [
                        'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
                    ]);

                    return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
                }

                try {
                    \unlink($filePath);
                } catch (\Throwable $e) {
                    $this->addFlash('error', $this->translator->trans('ui.source.error_delete'));
                    $this->logger->alert('An error occurred while deleting the file', [
                        'message'  => $e->getMessage(),
                        'source'   => $source,
                        'filepath' => $filePath,
                    ]);
                }

                $this->entityManager->remove($source);
            }

            $this->entityManager->flush();
        }

        if (1 === $quantity) {
            $this->addFlash('success', $this->translator->trans('ui.source.deleted_one'));
        } else {
            $this->addFlash('success', $this->translator->trans('ui.source.deleted_many', ['%count%' => $quantity]));
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }
}
