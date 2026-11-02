<?php

declare(strict_types=1);

namespace App\Controller\Ui\Profile;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ui/profile/theme', name: 'ui_profile_theme')]
final class ThemeController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    public function __invoke(): Response
    {
        return $this->render('ui/profile/theme.html.twig');
    }
}
