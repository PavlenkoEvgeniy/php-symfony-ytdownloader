<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DownloadSettings;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Form\DownloadSettingsForm;
use App\Repository\DownloadSettingsRepositoryInterface;
use App\Service\AuditLoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $settings = $this->settingsRepository->getSettings();

        $form = $this->createForm(DownloadSettingsForm::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if (!$form->isValid()) {
                $this->auditLogger->log(
                    action: AuditAction::SETTINGS_UPDATE,
                    channel: AuditChannel::ADMIN,
                    success: false,
                    metadata: ['reason' => 'invalid_csrf'],
                );

                $this->addFlash('error', $this->translator->trans('admin.flash.invalid_csrf'));

                return $this->redirectToRoute('admin_settings');
            }

            $this->persist($settings);

            $this->auditLogger->log(
                action: AuditAction::SETTINGS_UPDATE,
                channel: AuditChannel::ADMIN,
                metadata: [
                    'web_intake_enabled'        => $settings->getWebIntakeEnabled(),
                    'progress_interval_minutes' => $settings->getProgressIntervalMinutes(),
                ],
            );

            $this->addFlash('success', $this->translator->trans('admin.flash.settings_saved'));

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'form' => $form,
        ]);
    }

    private function persist(DownloadSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }
}
