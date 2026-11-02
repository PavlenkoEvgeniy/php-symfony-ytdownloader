<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/logout', name: 'security_logout')]
final class LogoutController extends AbstractController
{
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('security_login');
    }
}
