<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
    {
        $this->addFlash($request, 'warning', $this->translator?->trans('security.access_denied') ?? 'Access denied.');

        return new RedirectResponse($this->urlGenerator->generate('ui_download_index'));
    }

    private function addFlash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof Session) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
