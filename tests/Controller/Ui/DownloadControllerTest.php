<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/ui/download');
        $this->assertResponseRedirects();
    }

    public function testIndexPageDisplaysForm(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/download');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="download_form[link]"]');
        $this->assertSelectorExists('select[name="download_form[quality]"]');
    }

    public function testIndexPageShowsPreviousQualitySelection(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        // First, set a quality preference
        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => 'https://www.youtube.com/watch?v=example',
            'download_form[quality]' => 'best',
        ]);
        $this->client->submit($form);

        // Second request should remember the quality
        $crawler        = $this->client->request(Request::METHOD_GET, '/ui/download');
        $selectedOption = $crawler->filter('select[name="download_form[quality]"] option[selected]');
        $this->assertStringContainsString('best', $selectedOption->attr('value') ?: '');
    }

    public function testDownloadFormValidatesRequiredFields(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        // Note: Can't test empty quality directly as it's a choice field
        // Just test empty link
        $form = $crawler->filter('form')->form();
        $form->get('download_form[link]')->setValue('');

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDownloadFormValidatesUrlFormat(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => 'not-a-valid-url',
            'download_form[quality]' => 'best',
        ]);

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDownloadFormValidatesUrlLength(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        // Test with a very long URL exceeding 255 characters
        $longUrl = 'https://' . \str_repeat('a', 300) . '.com';
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => $longUrl,
            'download_form[quality]' => 'best',
        ]);

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDownloadFormAcceptsValidSubmission(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'download_form[quality]' => 'best',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertStringContainsString('Video was added to queue', $this->client->getResponse()->getContent() ?: '');
    }

    public function testDownloadFormAcceptsAllQualityOptions(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $qualities = ['best', 'moderate', 'poor', 'audio'];

        foreach ($qualities as $quality) {
            $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
            $form    = $crawler->filter('form')->form([
                'download_form[link]'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'download_form[quality]' => $quality,
            ]);

            $this->client->submit($form);
            $this->assertResponseRedirects('/ui/source');
            $this->client->followRedirect();
        }
    }

    public function testIndexPageDisplaysQueueStatistics(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/download');

        $this->assertResponseIsSuccessful();
        // Check that statistics are displayed in the page
        $content = $this->client->getResponse()->getContent() ?: '';
        $this->assertStringContainsString('Statistics', $content);
    }
}
