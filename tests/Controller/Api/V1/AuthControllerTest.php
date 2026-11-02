<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * Verifies login returns a non-empty JWT for valid credentials.
     */
    public function testLoginReturnsToken(): void
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v1/auth/login', [
            'email'    => 'admin@admin.local',
            'password' => 'admin123456',
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    /**
     * Verifies the profile endpoint rejects unauthenticated requests.
     */
    public function testMeRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/auth/me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Verifies the profile endpoint returns the authenticated admin user.
     */
    public function testMeReturnsUserWithValidToken(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v1/auth/me', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin@admin.local', $data['email'] ?? null);
        $this->assertContains('ROLE_ADMIN', $data['roles'] ?? []);
    }

    /**
     * Verifies logout with a valid token returns HTTP 200.
     */
    public function testLogoutReturnsOk(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_POST, '/api/v1/auth/logout', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v1/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return (string) ($data['token'] ?? '');
    }
}
