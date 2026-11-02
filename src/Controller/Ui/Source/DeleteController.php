<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    '/ui/source/delete/{id}',
    name: 'ui_source_delete',
    requirements: ['id' => Requirement::DIGITS],
    methods: [Request::METHOD_POST],
)]
final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, Source $source): Response
    {
        if (!$this->isCsrfTokenValid(\sprintf('delete%s', $source->getId()), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        $filePath = \sprintf('%s/%s', $source->getFilepath(), $source->getFilename());

        if (!\file_exists($filePath)) {
            $this->logger->alert('An error occurred while deleting the file', [
                'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
            ]);
            $this->addFlash('error', $this->translator->trans('ui.source.error_delete'));

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            \unlink($filePath);
            $this->addFlash('success', $this->translator->trans('ui.source.deleted'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('ui.source.error_delete'));
            $this->logger->alert('An error occurred while deleting the file', [
                'message'  => $e->getMessage(),
                'source'   => $source,
                'filepath' => $filePath,
            ]);

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($source);
        $this->entityManager->flush();

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }
}
