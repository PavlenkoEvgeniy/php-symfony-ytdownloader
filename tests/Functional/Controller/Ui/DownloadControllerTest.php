<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DownloadControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testIndexPageIsOpeningOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/youtube/download');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to youtube downloader');
    }

    public function testDownloadFromYoutubeMakeNewMessageOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ui/youtube/download');
        $this->assertResponseIsSuccessful();

        // Initial count messages in messenger_messages table
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $initialCount  = $entityManager->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM messenger_messages')
            ->fetchOne();

        $form = $crawler->filter('form')->form([
            'download[link]' => 'https://www.youtube.com/watch?v=przDcQe6n5o',
        ]);

        $this->client->submit($form);

        // Get the new count
        $newCount = $entityManager->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM messenger_messages')
            ->fetchOne();

        // Assert that count increased by 1
        $this->assertEquals($initialCount + 1, $newCount, 'Message was not created in database');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a', '+ Add new download');
    }

    public function testDownloadFromYoutubeWithNotValidLinkFails(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ui/youtube/download');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'download[link]' => '1234567890',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSelectorTextContains('div.invalid-feedback', 'This value is not a valid URL.');
    }
}
