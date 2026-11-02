<?php

declare(strict_types=1);

namespace App\Controller\Ui\Download;

use App\Form\DownloadForm;
use App\Helper\Helper;
use App\Service\DownloadDispatcherInterface;
use App\Service\QueueStatsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/ui/download', name: 'ui_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
final class IndexController extends AbstractController
{
    public function __construct(
        private readonly DownloadDispatcherInterface $downloadDispatcher,
        private readonly QueueStatsServiceInterface $queueStatsService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, SessionInterface $session): Response|RedirectResponse
    {
        $form = $this->createForm(DownloadForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoUrl = (string) $form->get('link')->getViewData();
            $quality  = (string) $form->get('quality')->getViewData();

            $session->set('lastSelectedQuality', $quality);

            $this->downloadDispatcher->dispatch($videoUrl, $quality);

            $this->addFlash('success', $this->translator->trans('ui.download.video_queued'));

            return $this->redirectToRoute('ui_source_index');
        }

        if ($session->has('lastSelectedQuality')) {
            $form->get('quality')->setData($session->get('lastSelectedQuality'));
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
