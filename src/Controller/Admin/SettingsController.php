<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DownloadSettings;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Form\DownloadSettingsForm;
use App\Helper\Helper;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\DiskSpacePolicyInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminRoute(path: '/settings', name: 'settings')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DownloadSettingsRepositoryInterface $settingsRepository,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly TranslatorInterface $translator,
        private readonly DiskSpacePolicyInterface $diskSpacePolicy,
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private readonly string $downloadsDir,
    ) {
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $settings = $this->settingsRepository->getSettings();

        $form   = $this->createForm(DownloadSettingsForm::class, $settings);
        $backup = clone $settings;
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                // The form already mapped submitted values onto the managed entity;
                // revert them so the audit flush cannot persist invalid data.
                $settings
                    ->setWebIntakeEnabled($backup->getWebIntakeEnabled())
                    ->setProgressIntervalMinutes($backup->getProgressIntervalMinutes())
                    ->setMinFreeDiskMb($backup->getMinFreeDiskMb());

                if ($this->hasRootFormErrors($form)) {
                    $this->auditLogger->log(
                        action: AuditAction::SETTINGS_UPDATE,
                        channel: AuditChannel::ADMIN,
                        success: false,
                        metadata: ['reason' => 'invalid_csrf'],
                    );

                    $this->addFlash('error', $this->translator->trans('admin.flash.invalid_csrf'));

                    return $this->redirectToRoute('admin_settings');
                }

                $this->auditLogger->log(
                    action: AuditAction::SETTINGS_UPDATE,
                    channel: AuditChannel::ADMIN,
                    success: false,
                    metadata: ['reason' => 'validation_failed'],
                );

                return $this->renderSettings($settings, $form);
            }

            $this->persist($settings);

            $this->auditLogger->log(
                action: AuditAction::SETTINGS_UPDATE,
                channel: AuditChannel::ADMIN,
                metadata: [
                    'web_intake_enabled'        => $settings->getWebIntakeEnabled(),
                    'progress_interval_minutes' => $settings->getProgressIntervalMinutes(),
                    'min_free_disk_mb'          => $settings->getMinFreeDiskMb(),
                ],
            );

            $this->addFlash('success', $this->translator->trans('admin.flash.settings_saved'));

            return $this->redirectToRoute('admin_settings');
        }

        return $this->renderSettings($settings, $form);
    }

    private function renderSettings(DownloadSettings $settings, FormInterface $form): Response
    {
        return $this->render('admin/settings.html.twig', [
            'form'             => $form,
            'settings'         => $settings,
            'diskSpace'        => Helper::getFreeSpace($this->downloadsDir),
            'diskSpaceLow'     => $this->diskSpacePolicy->isLow(),
            'diskMinMb'        => (int) \round((float) $this->diskSpacePolicy->getMinFreeBytes() / 1024.0 / 1024.0),
            'diskMinIsDefault' => null === $settings->getMinFreeDiskMb(),
        ]);
    }

    /**
     * Root-level form errors are CSRF failures and keep the old redirect
     * behavior; field errors are validation messages shown on the page.
     */
    private function hasRootFormErrors(FormInterface $form): bool
    {
        return $form->getErrors()->count() > 0;
    }

    private function persist(DownloadSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
