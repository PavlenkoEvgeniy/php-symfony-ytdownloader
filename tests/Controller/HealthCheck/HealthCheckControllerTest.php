<?php

declare(strict_types=1);

namespace App\Tests\Controller\HealthCheck;

use Helmich\JsonAssert\JsonAssertions;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthCheckControllerTest extends WebTestCase
{
    use JsonAssertions;

    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHealthCheckIsOk(): void
    {
        $this->client->request(Request::METHOD_GET, '/health-check');
        $response = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('OK', $response['status']);
    }
}
