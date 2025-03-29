<?php

namespace App\Controller\Ui;

use App\Entity\Source;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;
use App\Form\DownloadType;
use App\Service\YoutubeAuthService;
use App\Repository\SourceRepository;
use Symfony\Component\Panther\Client;
use App\Service\DiskSpaceCheckerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class YoutubeDownloadController extends AbstractController
{
    #[Route('/ui/youtube/download', name: 'ui_youtube_download_index', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(
        Request $request,
        SourceRepository $sourceRepository,
        EntityManagerInterface $entityManager,
        DiskSpaceCheckerService $diskSpaceCheckerService,
        string $youtubeLogin,
        string $youtubePassword,
        Client $client,
    ): Response|RedirectResponse {
        $form = $this->createForm(DownloadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url = $form->get('link')->getViewData();

            try {
                $cookies = $this->getYoutubeCookies($youtubeLogin, $youtubePassword, $client);
            } catch (\Exception $e) {
                $this->addFlash('error', "Error downloading video: {$e->getMessage()}");

                return $this->redirectToRoute('ui_source_index');
            }

            $yt = new YoutubeDl();

            $projectDir = $this->getParameter('kernel.project_dir');

            $collection = $yt->download(
                Options::create()
                    ->downloadPath("{$projectDir}/var/downloads")
                    ->url($url)
                    ->format('bestvideo[height<=1080]+bestaudio/best')
                    ->mergeOutputFormat('mp4')
                    ->output('%(title)s.%(ext)s')
                    ->cookies($cookies)
            );

            foreach ($collection->getVideos() as $video) {
                if (null !== $video->getError()) {
                    $this->addFlash('error', 'Error downloading video: cannot make playlist file');
                } else {
                    $filename = $video->getFile()->getBasename();
                    $path     = $video->getFile()->getPath();
                    $size     = $video->getFile()->getSize();

                    $source = $sourceRepository->findOneByFilename($filename);

                    if (null === $source) {
                        $source = new Source();
                        $source
                            ->setFilename($filename)
                            ->setFilepath($path)
                            ->setSize((float) $size)
                        ;

                        $entityManager->persist($source);
                    }
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('ui_source_index');
        }

        return $this->render('ui/youtube_download/index.html.twig', [
            'form'       => $form,
            'disk_space' => $diskSpaceCheckerService->getFreeSpace(),
        ]);
    }

    public function getYoutubeCookies(string $youtubeLogin, string $youtubePassword, Client $client): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        $authenticator = new YoutubeAuthService($projectDir, $client);

        $cookiesFile = $authenticator->authenticate($youtubeLogin, $youtubePassword);

        return $cookiesFile;
    }
}
