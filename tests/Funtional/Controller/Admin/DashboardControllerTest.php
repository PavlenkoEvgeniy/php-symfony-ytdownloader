<?php

declare(strict_types=1);

namespace App\Tests\Funtional\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DashboardControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->userRepository   = $this->getContainer()->get(UserRepository::class);
    }

    public function testIndexPageIsOpeningForAdminOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Statistics');
    }

    public function testIndexPageIsNotAvailableForNonAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testIndexPageIsNotAvailableForDisabledAdmin(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form             = $crawler->selectButton('Sign in')->form();
        $form['email']    = 'admin.disabled@admin.local';
        $form['password'] = 'admin.disabled123456';
        $this->client->submit($form);

        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');

        $this->client->request('GET', '/admin');

        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Please sign in');
    }
}
