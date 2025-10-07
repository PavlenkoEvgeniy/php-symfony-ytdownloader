<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
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

        $this->client->request('GET', '/ui/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Profile');
        $this->assertSelectorTextContains('h3', 'Change password:');
    }
}
