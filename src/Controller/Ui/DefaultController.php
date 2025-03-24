<?php

namespace App\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'ui_default')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('ui_youtube_download_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
