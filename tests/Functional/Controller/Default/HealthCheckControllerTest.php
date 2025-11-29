<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Default;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthCheckControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHealthCheckIsOk(): void
    {
        $this->client->request(Request::METHOD_GET, '/health');
        $response = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('status', $response);
    }
}
