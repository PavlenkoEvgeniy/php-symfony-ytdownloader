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

    public function testLoginReturnsToken(): void
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v1/auth/login', [
            'email'    => 'admin@admin.local',
            'password' => 'admin123456',
        ]);

        $this->assertResponseIsSuccessful();

        $data = \json_decode($this->client->getResponse()->getContent() ?? '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testMeRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/auth/me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMeReturnsUserWithValidToken(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v1/auth/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = \json_decode($this->client->getResponse()->getContent() ?? '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin@admin.local', $data['email'] ?? null);
        $this->assertContains('ROLE_ADMIN', $data['roles'] ?? []);
    }

    public function testLogoutReturnsOk(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_POST, '/api/v1/auth/logout', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
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

        $data = \json_decode($this->client->getResponse()->getContent() ?? '', true, 512, JSON_THROW_ON_ERROR);

        return (string) ($data['token'] ?? '');
    }
}
