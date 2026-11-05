<?php

declare(strict_types=1);

namespace App\Controller\Ui;

use App\Service\DownloadIntakePolicyInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'ui_main')]
final class MainController extends AbstractController
{
    public function __construct(
        private readonly DownloadIntakePolicyInterface $intakePolicy,
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        if (!$this->intakePolicy->isWebIntakeEnabled()) {
            // Temporary: the target depends on a runtime switch and must not be cached like the default one.
            return $this->redirectToRoute('ui_source_index', [], Response::HTTP_FOUND);
        }

        return $this->redirectToRoute('ui_download_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
