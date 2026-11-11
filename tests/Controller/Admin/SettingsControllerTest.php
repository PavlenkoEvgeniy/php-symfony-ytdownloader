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
        $this->assertSelectorTextContains('body', 'Web download intake');
        $this->assertSelectorTextContains('body', 'Telegram download progress');

        $field = $crawler->filter('input[name="download_settings_form[progressIntervalMinutes]"]');
        $this->assertCount(1, $field);
        $this->assertSame('5', $field->attr('value'));
        $this->assertSelectorTextContains('body', 'Current Telegram progress interval: every 5 minute(s)');
        $this->assertSelectorExists('div.form-check.form-switch input[role="switch"]');

        $switch = $crawler->filter('div.form-check.form-switch input[role="switch"]');
        $this->assertCount(1, $switch);
        $this->assertStringContainsString('confirm(', (string) $switch->attr('onclick'));
    }

    /**
     * Verifies saving the form disables the web intake and records the change.
     */
    public function testToggleDisablesWebIntakeAndIsAudited(): void
    {
        $this->setWebIntakeEnabled(true);
        $this->setMinFreeDiskMb(null);
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
            [
                'web_intake_enabled'        => false,
                'progress_interval_minutes' => 5,
                'min_free_disk_mb'          => null,
            ],
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

    /**
     * Verifies the page shows free space and the deployment default as the
     * effective minimum while no threshold has been saved.
     */
    public function testDiskStateShowsDeploymentDefaultWhileUnset(): void
    {
        $this->setMinFreeDiskMb(null);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Disk space');
        $this->assertSelectorTextContains('body', 'Free space in the downloads folder');
        $this->assertSelectorTextContains('body', 'Effective minimum:');
        $this->assertSelectorTextContains('body', '(deployment default)');

        $field = $crawler->filter('input[name="download_settings_form[minFreeDiskMb]"]');
        $this->assertCount(1, $field);
        $this->assertSame('', (string) $field->attr('value'));
    }

    /**
     * Verifies saving a threshold persists it, shows it as set here, and
     * clearing the field returns to the deployment default.
     */
    public function testSavesThresholdAndClearingReturnsToDefault(): void
    {
        $this->setMinFreeDiskMb(null);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['download_settings_form[minFreeDiskMb]' => '100']);

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        $this->assertSame(100, $this->currentSettings()->getMinFreeDiskMb());
        $this->assertSelectorTextContains('body', 'Effective minimum: 100 MB (set here)');

        $event = $this->latestSettingsEvent();
        $this->assertInstanceOf(AuditEvent::class, $event);
        $this->assertSame(100, $event->getMetadata()['min_free_disk_mb']);

        $form = $this->client->getCrawler()->filter('form')->form();
        $form->setValues(['download_settings_form[minFreeDiskMb]' => '']);

        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/settings');
        $this->client->followRedirect();
        $this->assertNull($this->currentSettings()->getMinFreeDiskMb());
        $this->assertSelectorTextContains('body', '(deployment default)');
    }

    /**
     * Verifies a negative threshold is rejected and reported on the page.
     */
    public function testNegativeThresholdIsRejected(): void
    {
        $this->setMinFreeDiskMb(null);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['download_settings_form[minFreeDiskMb]' => '-1']);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'This value should be greater than or equal to 0.');
        $this->assertNull($this->currentSettings()->getMinFreeDiskMb());
    }

    /**
     * Verifies a threshold above the supported maximum is rejected and reported.
     */
    public function testThresholdAboveMaximumIsRejected(): void
    {
        $this->setMinFreeDiskMb(null);
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/settings');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['download_settings_form[minFreeDiskMb]' => '1048577']);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'This value should be less than or equal to 1048576.');
        $this->assertNull($this->currentSettings()->getMinFreeDiskMb());
    }

    /**
     * Verifies the page reports free space below a threshold above real usage.
     */
    public function testPageReportsFreeSpaceBelowMinimum(): void
    {
        $this->setMinFreeDiskMb(999999999);
        $this->loginAsAdmin();

        try {
            $this->client->request(Request::METHOD_GET, '/admin/settings');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', 'Free space is below the effective minimum.');
            $this->assertSelectorTextContains('body', 'Effective minimum: 999999999 MB (set here)');
        } finally {
            $this->setMinFreeDiskMb(null);
        }
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

    private function setMinFreeDiskMb(?int $minFreeDiskMb): void
    {
        $this->entityManager->clear();

        $settings = $this->settingsRepository->getSettings();
        $settings->setMinFreeDiskMb($minFreeDiskMb);

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
