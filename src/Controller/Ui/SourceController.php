<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Entity\Source;
use App\Form\SourceForm;
use App\Repository\SourceRepository;
use App\Service\MessengerQueueCounterService;
use App\Service\RabbitMQApiQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SourceController extends AbstractController
{
    public function __construct(
        private readonly string $downloadsDir,
        private readonly MessengerQueueCounterService $messengerQueueCounter,
        private readonly RabbitMQApiQueueService $rabbitMQApiQueueService,
    ) {
    }

    #[Route('/ui/source', name: 'ui_source_index', methods: [Request::METHOD_GET])]
    public function index(
        SourceRepository $sourceRepository,
        SessionInterface $session,
        #[MapQueryParameter] string $order = 'desc',
    ): Response {
        if ($session->has('lastSelectedOrder')) {
            $order = $session->get('lastSelectedOrder');
        }

        $totalPending = $this->messengerQueueCounter->getQueueCount();

        $totalInProgress = $this->rabbitMQApiQueueService->getProcessingMessagesCount();

        return $this->render('ui/source/index.html.twig', [
            'sources'         => $sourceRepository->findBy([], ['createdAt' => $order]),
            'order'           => $order,
            'totalPending'    => $totalPending,
            'totalInProgress' => $totalInProgress,
        ]);
    }

    #[Route('/ui/source/new', name: 'ui_source_new', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $source = new Source();
        $form   = $this->createForm(SourceForm::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($source);
            $em->flush();

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
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        Source $source,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ): Response {
        $oldFilename = \sprintf('%s/%s', $this->downloadsDir, $source->getFilename());

        $form = $this->createForm(SourceForm::class, $source);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newFileName = \sprintf('%s/%s', $this->downloadsDir, $form->get('filename')->getData());

            if (!\file_exists($oldFilename)) {
                $logger->alert('An error occurred while editing the file', [
                    'message' => \sprintf('File not found, not possible to edit file: %s', $oldFilename),
                ]);
                throw new NotFoundHttpException(\sprintf('File not found: %s', $oldFilename));
            }

            if ($newFileName !== $oldFilename) {
                try {
                    \rename($oldFilename, $newFileName);
                } catch (\Exception $e) {
                    $logger->alert('An error occurred while deleting the file', [
                        'message'     => $e->getMessage(),
                        'source'      => $source,
                        'oldFilename' => $oldFilename,
                        'newFileName' => $newFileName,
                    ]);
                    throw new IOException($e->getMessage());
                }
            }

            $em->flush();

            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ui/source/edit.html.twig', [
            'source' => $source,
            'form'   => $form,
        ]);
    }

    #[Route('/ui/source/delete/{id}', name: 'ui_source_delete', methods: [Request::METHOD_POST])]
    public function delete(
        Request $request,
        Source $source,
        EntityManagerInterface $em,
        LoggerInterface $logger,
    ): Response {
        if ($this->isCsrfTokenValid('delete' . (string) $source->getId(), $request->getPayload()->getString('_token'))) {
            $filePath = $source->getFilepath() . '/' . $source->getFilename();

            if (!\file_exists($filePath)) {
                $logger->alert('An error occurred while deleting the file', [
                    'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
                ]);
                throw new NotFoundHttpException(\sprintf('File not found: %s', $filePath));
            }

            try {
                \unlink($filePath);
                $this->addFlash('success', 'File was deleted');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while deleting the file');
                $logger->alert('An error occurred while deleting the file', [
                    'message'  => $e->getMessage(),
                    'source'   => $source,
                    'filepath' => $filePath,
                ]);
            }

            $em->remove($source);
            $em->flush();
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ui/source/delete-all', name: 'ui_source_delete_all', methods: [Request::METHOD_POST])]
    public function deleteAll(
        Request $request,
        EntityManagerInterface $em,
        SourceRepository $sourceRepository,
        LoggerInterface $logger,
    ): RedirectResponse {
        if ($this->isCsrfTokenValid('delete_all', $request->getPayload()->getString('_token'))) {
            $sources = $sourceRepository->findAll();

            $resultMessage = [];

            foreach ($sources as $source) {
                $filePath = $source->getFilepath() . '/' . $source->getFilename();

                if (!\file_exists($filePath)) {
                    $this->addFlash('error', 'An error occurred while deleting the file');
                    $logger->alert('An error occurred while deleting the file', [
                        'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
                    ]);
                    throw new NotFoundHttpException('File not found');
                }

                try {
                    \unlink($filePath);
                    $this->addFlash('success', 'File was deleted');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'An error occurred while deleting the file');
                    $logger->alert('An error occurred while deleting the file', [
                        'message'  => $e->getMessage(),
                        'source'   => $source,
                        'filepath' => $filePath,
                    ]);
                }

                $em->remove($source);
            }
            $em->flush();
        }

        return $this->redirectToRoute('ui_source_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ui/source/download/{id}', name: 'ui_source_download', methods: [Request::METHOD_GET])]
    public function download(
        Source $source,
        LoggerInterface $logger,
    ): BinaryFileResponse {
        $filePath = $source->getFilepath() . '/' . $source->getFilename();

        if (!\file_exists($filePath)) {
            $logger->alert('An error occurred while deleting the file', [
                'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
            ]);
            throw new NotFoundHttpException('File not found');
        }

        $response = new BinaryFileResponse($filePath);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $source->getFilename()
        );

        return $response;
    }
}
