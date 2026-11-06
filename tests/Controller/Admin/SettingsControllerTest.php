<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\AuditEvent;
use App\Entity\DownloadSettings;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;

final class SettingsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;
    private DownloadSettingsRepositoryInterface $settingsRepository;
    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        $this->client             = static::createClient();
        $this->userRepository     = $this->getContainer()->get(UserRepository::class);
        $this->settingsRepository = $this->getContainer()->get(DownloadSettingsRepositoryInterface::class);
        $this->entityManager      = $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Verifies the settings page redirects anonymous visitors to login.
     */
    public function testPageRedirectsAnonymousToLogin(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/settings');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Verifies an admin sees the web download intake switch.
     */
    public function testPageRendersForAdmin(): void
    {
        $this->setProgressInterval(5);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Accept new downloads from the web');

        $field = $crawler->filter('input[name="download_settings_form[progressIntervalMinutes]"]');
        $this->assertCount(1, $field);
        $this->assertSame('5', $field->attr('value'));
        $this->assertSelectorTextContains('body', 'Current Telegram progress interval: every 5 minute(s)');
        $this->assertSelectorExists('div.form-check.form-switch input[role="switch"]');
    }

    /**
     * Verifies saving the form disables the web intake and records the change.
     */
    public function testToggleDisablesWebIntakeAndIsAudited(): void
    {
        $this->setWebIntakeEnabled(true);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
        $form    = $crawler->filter('form')->form();

        $field = $form->get('download_settings_form[webIntakeEnabled]');
        $this->assertInstanceOf(ChoiceFormField::class, $field);
        $field->untick();

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-success', 'Settings saved.');

        $this->assertFalse($this->currentSettings()->getWebIntakeEnabled());

        $event = $this->latestSettingsEvent();

        $this->assertInstanceOf(AuditEvent::class, $event);
        $this->assertSame(AuditAction::SETTINGS_UPDATE->value, $event->getAction());
        $this->assertSame(AuditChannel::ADMIN->value, $event->getChannel());
        $this->assertTrue($event->isSuccess());
        $this->assertSame(
            ['web_intake_enabled' => false, 'progress_interval_minutes' => 5],
            $event->getMetadata(),
        );

        $this->setWebIntakeEnabled(true);
    }

    /**
     * Verifies saving the progress interval persists it and records the change.
     */
    public function testSavesProgressIntervalAndIsAudited(): void
    {
        $this->setProgressInterval(5);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['download_settings_form[progressIntervalMinutes]' => '0']);

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Current Telegram progress interval: disabled');
        $this->assertSame(0, $this->currentSettings()->getProgressIntervalMinutes());

        $event = $this->latestSettingsEvent();

        $this->assertInstanceOf(AuditEvent::class, $event);
        $this->assertSame(0, $event->getMetadata()['progress_interval_minutes']);

        $this->setProgressInterval(5);
    }

    /**
     * Verifies saving recreates the settings row when it is missing.
     */
    public function testSavingCreatesMissingSettingsRow(): void
    {
        $this->entityManager->getConnection()->executeStatement('DELETE FROM download_settings');
        $this->entityManager->clear();

        $this->loginAsAdmin();

        try {
            $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
            $form    = $crawler->filter('form')->form();

            $field = $form->get('download_settings_form[webIntakeEnabled]');
            $this->assertInstanceOf(ChoiceFormField::class, $field);
            $field->untick();

            $this->client->submit($form);

            $this->assertResponseRedirects('/admin/settings');
            $this->assertFalse($this->currentSettings()->getWebIntakeEnabled());
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    /**
     * Verifies an invalid CSRF token changes nothing and is reported.
     */
    public function testInvalidCsrfKeepsValue(): void
    {
        $this->setWebIntakeEnabled(true);
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_POST, '/admin/settings', [
            'download_settings_form' => [
                'webIntakeEnabled' => '1',
            ],
        ]);

        $this->assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'Invalid CSRF token.');

        $this->assertTrue($this->currentSettings()->getWebIntakeEnabled());
    }

    private function loginAsAdmin(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);
    }

    private function setWebIntakeEnabled(bool $enabled): void
    {
        $this->entityManager->clear();

        $settings = $this->settingsRepository->getSettings();
        $settings->setWebIntakeEnabled($enabled);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    private function setProgressInterval(int $minutes): void
    {
        $this->entityManager->clear();

        $settings = $this->settingsRepository->getSettings();
        $settings->setProgressIntervalMinutes($minutes);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    private function currentSettings(): DownloadSettings
    {
        $this->entityManager->clear();

        return $this->settingsRepository->getSettings();
    }

    private function latestSettingsEvent(): ?AuditEvent
    {
        return $this->entityManager
            ->getRepository(AuditEvent::class)
            ->findOneBy(
                ['action' => AuditAction::SETTINGS_UPDATE->value],
                ['id' => 'DESC'],
            );
    }
}
