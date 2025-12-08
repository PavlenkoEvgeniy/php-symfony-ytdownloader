<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

readonly class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[\Override]
    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?RedirectResponse
    {
        $this->addFlash($request, 'warning', 'Access denied.');

        return new RedirectResponse($this->urlGenerator->generate('ui_download_index'));
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    private function addFlash(Request $request, string $type, string $message): void
    {
        $request->getSession()->getFlashBag()->add($type, $message);
    }
}
