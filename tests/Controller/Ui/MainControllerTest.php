<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Repository\DownloadSettingsRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MainControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private DownloadSettingsRepositoryInterface $settingsRepository;
    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        $this->client             = static::createClient();
        $this->settingsRepository = $this->getContainer()->get(DownloadSettingsRepositoryInterface::class);
        $this->entityManager      = $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Verifies the root route permanently redirects to the download page.
     */
    public function testRootRedirectsToDownloadPage(): void
    {
        $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('/ui/download');
    }

    /**
     * Verifies the root route redirects to the library when the web intake is off.
     */
    public function testRootRedirectsToLibraryWhenWebIntakeDisabled(): void
    {
        $this->setWebIntakeEnabled(false);

        try {
            $this->client->request(Request::METHOD_GET, '/');

            $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
            $this->assertResponseRedirects('/ui/source');
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    private function setWebIntakeEnabled(bool $enabled): void
    {
        $settings = $this->settingsRepository->getSettings();
        $settings->setWebIntakeEnabled($enabled);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
