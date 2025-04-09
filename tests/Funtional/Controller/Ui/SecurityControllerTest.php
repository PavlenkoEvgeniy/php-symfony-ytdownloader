<?php

namespace App\Tests\Funtional\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testLoginIsOk(): void
    {
        $this->client->request(Request::METHOD_GET, '/');
        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);

        $this->client->request(Request::METHOD_GET, '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');

        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/youtube/download');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to youtube downloader');
    }

    public function testLoginCredentialsAreNotOk(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/login');

        $form = $crawler->filter('form')->form([
            'email'    => 'not_valid_user@example.local',
            'password' => 'not_valid_password',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('div.alert', 'Invalid credentials.');
    }
}
