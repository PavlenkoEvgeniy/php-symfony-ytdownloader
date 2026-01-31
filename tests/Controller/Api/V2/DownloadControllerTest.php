<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V2;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/v2/download/create');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateValidatesPayload(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/download/create', [
            'url'     => 'not-a-url',
            'quality' => 'best',
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateAcceptsValidRequest(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/download/create', [
            'url'     => 'https://example.com',
            'quality' => 'moderate',
        ], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/login', [
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
