<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\AccessDeniedHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedHandlerTest extends TestCase
{
    public function testHandleAddsFlashAndRedirects(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('ui_download_index')
            ->willReturn('/ui/download');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $handler  = new AccessDeniedHandler($urlGenerator);
        $response = $handler->handle($request, new AccessDeniedException());

        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/ui/download', $response->headers->get('location'));

        /** @var Session $session */
        $session  = $request->getSession();
        $flashBag = $session->getFlashBag();

        $this->assertInstanceOf(FlashBag::class, $flashBag);
        $this->assertSame(['Access denied.'], $flashBag->get('warning'));
    }

    public function testHandleWithoutSessionStillRedirects(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('ui_download_index')
            ->willReturn('/ui/download');

        $request = new Request();

        $handler  = new AccessDeniedHandler($urlGenerator);
        $response = $handler->handle($request, new AccessDeniedException());

        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/ui/download', $response->headers->get('location'));
    }
}
