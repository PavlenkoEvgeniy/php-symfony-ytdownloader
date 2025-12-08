<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class MainControllerTest extends WebTestCase
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

        $this->client->request('GET', '/ui/download');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Please paste YouTube link into the form bellow:');
    }

    public function testDownloadFromYoutubeWithNotValidLinkFails(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ui/download');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter(selector: 'form')->form([
            'download_form[link]' => '1234567890',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSelectorTextContains('div.invalid-feedback', 'This value is not a valid URL.');
    }
}
