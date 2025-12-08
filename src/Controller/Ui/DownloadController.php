<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Form\DownloadForm;
use App\Helper\Helper;
use App\Message\DownloadMessage;
use App\Repository\LogRepository;
use App\Service\MessengerQueueCounterService;
use App\Service\RabbitMQApiQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class DownloadController extends AbstractController
{
    /**
     * @throws ExceptionInterface
     */
    #[Route('/ui/download', name: 'ui_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(
        Request $request,
        MessageBusInterface $bus,
        MessengerQueueCounterService $messengerQueueCounter,
        SessionInterface $session,
        LogRepository $logRepository,
        RabbitMQApiQueueService $rabbitMQApiQueueService,
    ): Response|RedirectResponse {
        $form = $this->createForm(DownloadForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoUrl = $form->get('link')->getViewData();
            $quality  = $form->get('quality')->getViewData();

            $session->set('lastSelectedQuality', $quality);

            $bus->dispatch(new DownloadMessage($videoUrl, $quality));

            $this->addFlash('success', 'Video was added to queue.');

            return $this->redirectToRoute('ui_source_index');
        } else {
            if ($session->has('lastSelectedQuality')) {
                $form->get('quality')->setData(
                    $session->get('lastSelectedQuality')
                );
            }
        }

        $totalPendingDownloads    = $messengerQueueCounter->getQueueCount();
        $totalInProgressDownloads = $rabbitMQApiQueueService->getProcessingMessagesCount();
        $totalSuccessDownloads    = $logRepository->getTotalSuccessCount();
        $totalSizeDownloaded      = $logRepository->getTotalSize();

        return $this->render('ui/download/index.html.twig', [
            'form'                       => $form,
            'diskSpace'                  => Helper::getFreeSpace(),
            'totalPendingDownloads'      => $totalPendingDownloads,
            'totalInProgressDownloads'   => $totalInProgressDownloads,
            'totalSuccessDownloads'      => $totalSuccessDownloads,
            'totalDownloaded'            => Helper::formatBytes($totalSizeDownloaded),
        ]);
    }
}
