<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Exception\DownloadIntakeDisabledException;
use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Repository\UserRepository;
use App\Service\DiskSpacePolicy;
use App\Service\DiskSpacePolicyInterface;
use App\Service\DownloadDispatcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private DownloadSettingsRepositoryInterface $settingsRepository;
    private DownloadTaskRepositoryInterface $downloadTaskRepository;
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client                 = static::createClient();
        $this->userRepository         = $this->getContainer()->get(UserRepository::class);
        $this->settingsRepository     = $this->getContainer()->get(DownloadSettingsRepositoryInterface::class);
        $this->downloadTaskRepository = $this->getContainer()->get(DownloadTaskRepositoryInterface::class);
        $this->entityManager          = $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Verifies the download page redirects anonymous visitors to login.
     */
    public function testIndexRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/ui/download');
        $this->assertResponseRedirects();
    }

    /**
     * Verifies the download page renders the link and quality form.
     */
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

    /**
     * Verifies the page remembers the previously selected quality.
     */
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

    /**
     * Verifies an empty link is rejected with HTTP 422.
     */
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

    /**
     * Verifies a malformed URL is rejected with HTTP 422.
     */
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

    /**
     * Verifies a URL longer than the limit is rejected with HTTP 422.
     */
    public function testDownloadFormValidatesUrlLength(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        // Test with a very long URL exceeding 255 characters
        $longUrl = \sprintf('https://%s.com', \str_repeat('a', 300));
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => $longUrl,
            'download_form[quality]' => 'best',
        ]);

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Verifies a valid YouTube link is queued and the user is redirected.
     */
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

    /**
     * Verifies every quality option is accepted and redirects to sources.
     */
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

    /**
     * Verifies the download page renders queue statistics.
     */
    public function testIndexPageDisplaysQueueStatistics(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/download');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent() ?: '';
        $this->assertStringContainsString('Statistics', $content);
    }

    /**
     * Verifies the download page redirects to the library when the web intake is off.
     */
    public function testIndexRedirectsToLibraryWhenWebIntakeDisabled(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->setWebIntakeEnabled(false);

        try {
            $this->client->request(Request::METHOD_GET, '/ui/download');

            $this->assertResponseRedirects('/ui/source');

            $this->client->followRedirect();
            $this->assertSelectorTextContains('div.alert-warning', 'New downloads through the web are disabled.');
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    /**
     * Verifies a direct POST creates no task when the web intake is off.
     */
    public function testDownloadPostCreatesNoTaskWhenWebIntakeDisabled(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->setWebIntakeEnabled(false);

        try {
            $before = $this->countTasks();

            $this->client->request(Request::METHOD_POST, '/ui/download', [
                'download_form' => [
                    'link'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'quality' => 'best',
                ],
            ]);

            $this->assertResponseRedirects('/ui/source');
            $this->assertSame($before, $this->countTasks());
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    /**
     * Verifies the library hides the "Add new download" entry point when the intake is off.
     */
    public function testLibraryHidesAddDownloadButtonWhenWebIntakeDisabled(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->setWebIntakeEnabled(false);

        try {
            $this->client->request(Request::METHOD_GET, '/ui/source');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorNotExists('a[href="/ui/download"]');
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    /**
     * Verifies a download submission is rejected while the disk is under pressure.
     */
    public function testDownloadPostIsRejectedUnderDiskPressure(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $policy = $this->createStub(DiskSpacePolicyInterface::class);
        $policy->method('isLow')->willReturn(true);
        $policy->method('assertCanAccept')->willThrowException(InsufficientDiskSpaceException::create(0.0, 1));

        $this->client->disableReboot();
        $this->getContainer()->set(DiskSpacePolicy::class, $policy);

        try {
            $before = $this->countTasks();

            $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('div.alert-warning', 'Free disk space is below the minimum');

            $form = $crawler->filter('form')->form([
                'download_form[link]'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'download_form[quality]' => 'best',
            ]);

            $this->client->submit($form);

            $this->assertResponseRedirects('/ui/source');
            $this->client->followRedirect();
            $this->assertSelectorTextContains('div.alert-danger', 'Not enough free disk space to accept new downloads.');
            $this->assertSame($before, $this->countTasks());
        } finally {
            $this->client->enableReboot();
        }
    }

    /**
     * Verifies a submission rejected by the dispatcher redirects with a warning.
     */
    public function testDownloadPostRedirectsWhenIntakeClosesDuringDispatch(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $dispatcher = $this->createStub(DownloadDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willThrowException(DownloadIntakeDisabledException::create());

        $this->client->disableReboot();
        $this->getContainer()->set(DownloadDispatcherInterface::class, $dispatcher);

        try {
            $before = $this->countTasks();

            $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
            $this->assertResponseIsSuccessful();

            $form = $crawler->filter('form')->form([
                'download_form[link]'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'download_form[quality]' => 'best',
            ]);

            $this->client->submit($form);

            $this->assertResponseRedirects('/ui/source');
            $this->client->followRedirect();
            $this->assertSelectorTextContains('div.alert-warning', 'New downloads through the web are disabled.');
            $this->assertSame($before, $this->countTasks());
        } finally {
            $this->client->enableReboot();
        }
    }

    private function setWebIntakeEnabled(bool $enabled): void
    {
        $settings = $this->settingsRepository->getSettings();
        $settings->setWebIntakeEnabled($enabled);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    private function countTasks(): int
    {
        return \array_sum($this->downloadTaskRepository->getStatusCounts());
    }
}
