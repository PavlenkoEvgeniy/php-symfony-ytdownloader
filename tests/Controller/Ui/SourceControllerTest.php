<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SourceControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testSourcePageIsOpeningOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/source');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a', '+ Add new download');
    }

    public function testQueueStatsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/ui/source/queue-stats');
        $this->assertResponseRedirects();
    }

    public function testQueueStatsReturnsCounters(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/source/queue-stats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $content = $this->client->getResponse()->getContent() ?: '{}';
        $data    = \json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('queued', $data);
        $this->assertArrayHasKey('processing', $data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('tasks', $data);
    }
}
