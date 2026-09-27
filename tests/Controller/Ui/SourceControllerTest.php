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
        $this->assertArrayNotHasKey('tasks', $data);
    }

    /**
     * Regression test for the move of the active tasks table to the admin panel:
     * the UI page must not render the table for admins anymore, the admin panel owns it now.
     */
    public function testActiveTasksTableIsNotRenderedInUi(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/source');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-tasks-body]');
        $this->assertSelectorTextNotContains('body', 'Active tasks');
    }
}
