<?php

declare(strict_types=1);

namespace App\Tests\Funtional\Controller;

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

    public function userProvider(): array
    {
        return [
            [
                'username' => 'admin@admin.local',
                'password' => 'admin123456',
            ],
            [
                'username' => 'Admin@admin.local',
                'password' => 'admin123456',
            ],
            [
                'username' => 'Admin@Admin.Local',
                'password' => 'admin123456',
            ],
            [
                'username' => 'ADMIN@ADMIN.LOCAL',
                'password' => 'admin123456',
            ],
        ];
    }

    /**
     * @dataProvider userProvider
     */
    public function testLoginIsOk(string $username, string $password): void
    {
        $this->client->request(Request::METHOD_GET, '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');

        $crawler = $this->client->request(Request::METHOD_GET, '/login');

        $form = $crawler->filter('form')->form([
            'email'    => $username,
            'password' => $password,
        ]);

        $this->client->submit($form);

        $this->client->request(Request::METHOD_GET, '/ui/youtube/download');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to youtube downloader');
    }

    public function testLogoutIsOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/youtube/download');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to youtube downloader');

        $this->client->request(Request::METHOD_GET, '/logout');
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);

        $this->client->request(Request::METHOD_GET, '/ui/youtube/download');
        $this->client->followRedirect(true);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');
    }

    public function testLoginFailsWithNotOkCredentials(): void
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

    public function testIndexPageIsNotAvailableForDisabledUser(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form             = $crawler->selectButton('Sign in')->form();
        $form['email']    = 'user.disabled@test.local';
        $form['password'] = 'user.disabled123456';
        $this->client->submit($form);

        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');
    }
}
