<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Form\DownloadType;
use App\Helper\Helper;
use App\Message\DownloadMessage;
use App\Service\MessengerQueueCounterService;
use App\Service\QueueCounterService;
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
    #[Route('/ui/youtube/download', name: 'ui_youtube_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(
        Request $request,
        MessageBusInterface $bus,
        QueueCounterService $queueCounter,
        MessengerQueueCounterService $messengerQueueCounter,
        SessionInterface $session,
    ): Response|RedirectResponse {
        $form = $this->createForm(DownloadType::class);
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

        $queueTaskCount = $messengerQueueCounter->getQueueCount();

        return $this->render('ui/youtube_download/index.html.twig', [
            'form'           => $form,
            'diskSpace'      => Helper::getFreeSpace(),
            'queueTaskCount' => $queueTaskCount,
        ]);
    }
}
