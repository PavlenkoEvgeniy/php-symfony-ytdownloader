<?php

declare(strict_types=1);

namespace App\Controller\Ui\Download;

use App\Entity\User;
use App\Exception\DownloadIntakeDisabledException;
use App\Exception\InsufficientDiskSpaceException;
use App\Form\DownloadForm;
use App\Helper\Helper;
use App\Service\DiskSpacePolicyInterface;
use App\Service\DownloadDispatcherInterface;
use App\Service\DownloadIntakePolicyInterface;
use App\Service\QueueStatsServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/ui/download', name: 'ui_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
final class IndexController extends AbstractController
{
    public function __construct(
        private readonly DownloadDispatcherInterface $downloadDispatcher,
        private readonly DownloadIntakePolicyInterface $intakePolicy,
        private readonly DiskSpacePolicyInterface $diskSpacePolicy,
        private readonly QueueStatsServiceInterface $queueStatsService,
        private readonly TranslatorInterface $translator,
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private readonly string $downloadsDir,
    ) {
    }

    public function __invoke(
        Request $request,
        SessionInterface $session,
        #[CurrentUser]
        ?User $user,
    ): Response|RedirectResponse {
        if (!$this->intakePolicy->isWebIntakeEnabled()) {
            $this->addFlash('warning', $this->translator->trans('ui.download.web_intake_disabled'));

            return $this->redirectToRoute('ui_source_index');
        }

        $form = $this->createForm(DownloadForm::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $videoUrl = (string) $form->get('link')->getViewData();
            $quality  = (string) $form->get('quality')->getViewData();

            $session->set('lastSelectedQuality', $quality);

            try {
                $this->downloadDispatcher->dispatch($videoUrl, $quality, $user);
            } catch (DownloadIntakeDisabledException) {
                $this->addFlash('warning', $this->translator->trans('ui.download.web_intake_disabled'));

                return $this->redirectToRoute('ui_source_index');
            } catch (InsufficientDiskSpaceException) {
                $this->addFlash('error', $this->translator->trans('ui.download.insufficient_disk_space'));

                return $this->redirectToRoute('ui_source_index');
            }

            $this->addFlash('success', $this->translator->trans('ui.download.video_queued'));

            return $this->redirectToRoute('ui_source_index');
        }

        if ($session->has('lastSelectedQuality')) {
            $form->get('quality')->setData($session->get('lastSelectedQuality'));
        }

        $stats = $this->queueStatsService->getStats();

        return $this->render('ui/download/index.html.twig', [
            'form'                     => $form,
            'diskSpaceLow'             => $this->diskSpacePolicy->isLow(),
            'diskSpace'                => Helper::getFreeSpace($this->downloadsDir),
            'totalPendingDownloads'    => $stats['queued'],
            'totalInProgressDownloads' => $stats['processing'],
            'totalSuccessDownloads'    => $stats['success'],
            'totalDownloaded'          => Helper::formatBytes($stats['totalSize']),
        ]);
    }
}
