<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Entity\Source;
use App\Form\SourceType;
use App\Repository\LogRepository;
use App\Repository\SourceRepository;
use App\Service\QueueCounterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController extends AbstractController
{
    public function __construct(
        private readonly string $downloadsDir,
        private readonly LogRepository $logRepository,
        private readonly QueueCounterService $queueCounter,
    ) {
    }

    #[Route('/ui/source', name: 'ui_source_index', methods: [Request::METHOD_GET])]
    public function index(SourceRepository $sourceRepository): Response
    {
        $totalPending = $this->queueCounter->getQueueCount();

        $totalInProgress = $this->logRepository->getTotalInProgressCount();

        return $this->render('ui/source/index.html.twig', [
            'sources'         => $sourceRepository->findBy([], ['id' => 'ASC']),
            'totalPending'    => $totalPending,
            'totalInProgress' => $totalInProgress,
        ]);
    }

    #[Route('/ui/source/new', name: 'ui_source_new', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $source = new Source();
        $form   = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($source);
            $entityManager->flush();

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ui/source/new.html.twig', [
            'source' => $source,
            'form'   => $form,
        ]);
    }

    #[Route('/ui/source/{id}', name: 'ui_source_show', methods: [Request::METHOD_GET])]
    public function show(Source $source): Response
    {
        return $this->render('ui/source/show.html.twig', [
            'source' => $source,
        ]);
    }

    #[Route('/ui/source/{id}/edit', name: 'ui_source_edit', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function edit(Request $request, Source $source, EntityManagerInterface $entityManager): Response
    {
        $oldFilename = sprintf('%s/%s', $this->downloadsDir, $source->getFilename());

        $form = $this->createForm(SourceType::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newFileName = sprintf('%s/%s', $this->downloadsDir, $form->get('filename')->getData());

            if (!file_exists($oldFilename)) {
                throw new NotFoundHttpException('File not found');
            }

            if ($newFileName !== $oldFilename) {
                rename($oldFilename, $newFileName);
            }

            $entityManager->flush();

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ui/source/edit.html.twig', [
            'source' => $source,
            'form'   => $form,
        ]);
    }

    #[Route('/ui/source/delete/{id}', name: 'ui_source_delete', methods: [Request::METHOD_POST])]
    public function delete(Request $request, Source $source, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $source->getId(), $request->getPayload()->getString('_token'))) {
            $filePath = $source->getFilepath() . '/' . $source->getFilename();

            if (!file_exists($filePath)) {
                throw new NotFoundHttpException('File not found');
            }

            if (unlink($filePath)) {
                $this->addFlash('success', 'File was deleted');
            } else {
                $this->addFlash('error', 'Cannot delete file');
            }

            $entityManager->remove($source);
            $entityManager->flush();
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ui/source/delete-all', name: 'ui_source_delete_all', methods: [Request::METHOD_POST])]
    public function deleteAll(
        Request $request,
        EntityManagerInterface $entityManager,
        SourceRepository $sourceRepository,
    ): RedirectResponse {
        if ($this->isCsrfTokenValid('delete_all', $request->getPayload()->getString('_token'))) {
            $sources = $sourceRepository->findAll();

            $resultMessage = [];

            foreach ($sources as $source) {
                $filePath = $source->getFilepath() . '/' . $source->getFilename();

                if (!file_exists($filePath)) {
                    throw new NotFoundHttpException('File not found');
                }

                if (unlink($filePath)) {
                    $resultMessage['success'] = 'Files were deleted';
                } else {
                    $resultMessage['error'] = 'Cannot delete files';
                }

                $entityManager->remove($source);
            }
            $entityManager->flush();
        }

        if (array_key_exists('success', $resultMessage)) {
            $this->addFlash('success', $resultMessage['success']);
        } elseif (array_key_exists('error', $resultMessage)) {
            $this->addFlash('error', $resultMessage['error']);
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ui/source/download/{id}', name: 'ui_source_download', methods: [Request::METHOD_GET])]
    public function download(Source $source): BinaryFileResponse
    {
        $filePath = $source->getFilepath() . '/' . $source->getFilename();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('File was not found');
        }

        $response = new BinaryFileResponse($filePath);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $source->getFilename()
        );

        return $response;
    }
}
