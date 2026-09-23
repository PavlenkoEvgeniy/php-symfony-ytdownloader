<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Form\DownloadForm;
use App\Helper\Helper;
use App\Service\DownloadDispatcher;
use App\Service\QueueStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadController extends AbstractController
{
    public function __construct(
        private readonly DownloadDispatcher $downloadDispatcher,
        private readonly QueueStatsService $queueStatsService,
    ) {
    }

    #[Route('/ui/download', name: 'ui_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request, SessionInterface $session): Response|RedirectResponse
    {
        $form = $this->createForm(DownloadForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoUrl = $form->get('link')->getViewData();
            $quality  = $form->get('quality')->getViewData();

            $session->set('lastSelectedQuality', $quality);

            $this->downloadDispatcher->dispatch($videoUrl, $quality);

            $this->addFlash('success', 'Video was added to queue.');

            return $this->redirectToRoute('ui_source_index');
        }
        if ($session->has('lastSelectedQuality')) {
            $form->get('quality')->setData(
                $session->get('lastSelectedQuality')
            );
        }

        $stats = $this->queueStatsService->getStats();

        return $this->render('ui/download/index.html.twig', [
            'form'                     => $form,
            'diskSpace'                => Helper::getFreeSpace(),
            'totalPendingDownloads'    => $stats['queued'],
            'totalInProgressDownloads' => $stats['processing'],
            'totalSuccessDownloads'    => $stats['success'],
            'totalDownloaded'          => Helper::formatBytes($stats['totalSize']),
        ]);
    }
}
