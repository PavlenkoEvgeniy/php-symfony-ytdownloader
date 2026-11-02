<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\UserAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class UserAuthenticatorTest extends TestCase
{
    /**
     * Verifies authentication stores the normalized username and returns a passport.
     */
    public function testAuthenticateStoresLastUsernameAndReturnsPassport(): void
    {
        $urlGenerator  = $this->createStub(UrlGeneratorInterface::class);
        $authenticator = new UserAuthenticator($urlGenerator);

        $request = Request::create('/', Request::METHOD_POST, [
            'email'       => 'USER@Test.LOCAL',
            'password'    => 'secret',
            '_csrf_token' => 'csrf-token',
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertSame('user@test.local', $userBadge->getUserIdentifier());
        $this->assertSame('user@test.local', $request->getSession()->get(SecurityRequestAttributes::LAST_USERNAME));
    }

    /**
     * Verifies a stored target path wins over the default route.
     */
    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->never())
            ->method('generate');

        $authenticator = new UserAuthenticator($urlGenerator);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('_security.main.target_path', '/target');

        $token = $this->createStub(TokenInterface::class);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/target', $response->headers->get('location'));
    }

    /**
     * Verifies authentication without a target path redirects to downloads.
     */
    public function testOnAuthenticationSuccessRedirectsToDefault(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('ui_download_index')
            ->willReturn('/ui/download');

        $authenticator = new UserAuthenticator($urlGenerator);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $token = $this->createStub(TokenInterface::class);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/ui/download', $response->headers->get('location'));
    }

    /**
     * Verifies the authenticator redirects to the login route.
     */
    public function testGetLoginUrlUsesLoginRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(UserAuthenticator::LOGIN_ROUTE)
            ->willReturn('/login');

        $authenticator = new UserAuthenticator($urlGenerator);

        $request  = new Request();
        $response = $authenticator->start($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->headers->get('location'));
    }
}
