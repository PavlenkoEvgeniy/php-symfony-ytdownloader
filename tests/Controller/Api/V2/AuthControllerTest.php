<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V2;

use App\Controller\Api\V2\Auth\LoginController;
use App\Controller\Api\V2\Auth\LogoutController;
use App\Controller\Api\V2\Auth\MeController;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Verifies login returns a JWT and a refresh token with expiry.
     */
    public function testLoginReturnsTokenAndRefreshToken(): void
    {
        $data = $this->loginAndGetTokens('admin@admin.local', 'admin123456');

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertArrayHasKey('refresh_token_expires_at', $data);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['refresh_token']);
        $this->assertNotEmpty($data['refresh_token_expires_at']);
    }

    /**
     * Verifies the profile endpoint rejects unauthenticated requests.
     */
    public function testMeRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v2/auth/me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Verifies the profile endpoint returns the authenticated admin user.
     */
    public function testMeReturnsUserWithValidToken(): void
    {
        $data = $this->loginAndGetTokens('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v2/auth/me', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $data['token']),
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $payload = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin@admin.local', $payload['email'] ?? null);
        $this->assertContains('ROLE_ADMIN', $payload['roles'] ?? []);
    }

    /**
     * Verifies refreshing rotates the token pair.
     */
    public function testRefreshReturnsNewTokens(): void
    {
        $data = $this->loginAndGetTokens('admin@admin.local', 'admin123456');

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/refresh', [
            'refresh_token' => $data['refresh_token'],
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $payload = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('token', $payload);
        $this->assertArrayHasKey('refresh_token', $payload);
        $this->assertArrayHasKey('refresh_token_expires_at', $payload);
        $this->assertNotEmpty($payload['token']);
        $this->assertNotEmpty($payload['refresh_token']);
        $this->assertNotEmpty($payload['refresh_token_expires_at']);
        $this->assertNotSame($data['refresh_token'], $payload['refresh_token']);
    }

    /**
     * Verifies a refresh request without a token is rejected with HTTP 422.
     */
    public function testRefreshRequiresToken(): void
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/refresh', []);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Verifies an unknown refresh token is rejected with HTTP 401.
     */
    public function testRefreshRejectsInvalidToken(): void
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/refresh', [
            'refresh_token' => 'invalid-token',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Verifies logout with a valid token returns HTTP 200.
     */
    public function testLogoutReturnsOk(): void
    {
        $data = $this->loginAndGetTokens('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_POST, '/api/v2/auth/logout', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $data['token']),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    /**
     * Verifies the login controller itself answers with a translated message.
     */
    public function testLoginControllerReturnsMessage(): void
    {
        $controller = new LoginController($this->createTranslator('login handled'));
        $controller->setContainer(static::getContainer());

        $response = $controller();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('{"message":"login handled"}', (string) $response->getContent());
    }

    /**
     * Verifies the logout controller rejects anonymous callers.
     */
    public function testLogoutControllerRejectsAnonymousUser(): void
    {
        $controller = new LogoutController($this->createTranslator('unauthorized'));
        $controller->setContainer(static::getContainer());

        $response = $controller(null);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * Verifies the profile controller rejects anonymous callers.
     */
    public function testMeControllerRejectsAnonymousUser(): void
    {
        $controller = new MeController($this->createTranslator('unauthorized'));
        $controller->setContainer(static::getContainer());

        $response = $controller(null);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    private function createTranslator(string $message): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn($message);

        return $translator;
    }

    /**
     * @return array<string, string>
     */
    private function loginAndGetTokens(string $email, string $password): array
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);

        /** @var array<string, string> $data */
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
