<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'ui_main')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('ui_download_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
