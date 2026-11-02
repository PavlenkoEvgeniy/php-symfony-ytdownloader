<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use App\Repository\DownloadTaskRepositoryInterface;
use App\Service\AuditLoggerInterface;
use App\Service\OrphanFilePrunerInterface;
use App\Service\QueuePurgeServiceInterface;
use App\Service\QueueResetServiceInterface;
use App\Service\QueueStatsServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use OskarStark\Twig\ReadableFilesizeExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AuditLoggerInterface $auditLogger,
    ) {
    }

    #[\Override]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('admin_active_tasks');
    }

    /**
     * Display-only overview of the download queue: counters plus the recent active tasks.
     */
    #[AdminRoute(path: '/active-tasks', name: 'active_tasks')]
    public function activeTasks(
        QueueStatsServiceInterface $queueStatsService,
        DownloadTaskRepositoryInterface $downloadTaskRepository,
        OrphanFilePrunerInterface $orphanFilePruner,
    ): Response {
        return $this->render('admin/active_tasks.html.twig', [
            'stats'   => $queueStatsService->getStats(),
            'tasks'   => $downloadTaskRepository->getRecentActiveTasks(),
            'orphans' => $orphanFilePruner->findOrphans(),
        ]);
    }

    /**
     * Purge the queue: delete every queued task together with all pending transport messages.
     */
    #[AdminRoute(path: '/purge-queue', name: 'purge_queue', options: ['methods' => [Request::METHOD_POST]])]
    public function purgeQueue(Request $request, QueuePurgeServiceInterface $queuePurgeService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('purge_queue', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.flash.invalid_csrf'));

            $this->auditLogger->log(
                action: AuditAction::QUEUE_PURGE,
                channel: AuditChannel::ADMIN,
                success: false,
                metadata: ['reason' => 'invalid_csrf'],
            );

            return $this->redirectToRoute('admin_active_tasks');
        }

        $result = $queuePurgeService->purge();

        $this->auditLogger->log(
            action: AuditAction::QUEUE_PURGE,
            channel: AuditChannel::ADMIN,
            metadata: ['tasks' => $result['tasks'], 'messages' => $result['messages']],
        );

        $this->addFlash(
            'success',
            $this->translator->trans('admin.flash.purged', [
                '%count%'    => $result['tasks'],
                '%messages%' => $result['messages'],
            ]),
        );

        return $this->redirectToRoute('admin_active_tasks');
    }

    /**
     * Reset stuck work: processing tasks go back to queued and stuck in-flight
     * transport messages become deliverable again.
     */
    #[AdminRoute(path: '/reset-stuck-tasks', name: 'reset_stuck_tasks', options: ['methods' => [Request::METHOD_POST]])]
    public function resetStuckTasks(Request $request, QueueResetServiceInterface $queueResetService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('reset_stuck_tasks', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.flash.invalid_csrf'));

            $this->auditLogger->log(
                action: AuditAction::QUEUE_RESET,
                channel: AuditChannel::ADMIN,
                success: false,
                metadata: ['reason' => 'invalid_csrf'],
            );

            return $this->redirectToRoute('admin_active_tasks');
        }

        $result = $queueResetService->reset();

        $this->auditLogger->log(
            action: AuditAction::QUEUE_RESET,
            channel: AuditChannel::ADMIN,
            metadata: ['processing' => $result['processing'], 'messages' => $result['messages']],
        );

        $this->addFlash(
            'success',
            $this->translator->trans('admin.flash.reset', [
                '%count%'    => $result['processing'],
                '%messages%' => $result['messages'],
            ]),
        );

        return $this->redirectToRoute('admin_active_tasks');
    }

    /**
     * Delete orphan files: files in the downloads directory that no Source references.
     */
    #[AdminRoute(path: '/prune-orphans', name: 'prune_orphans', options: ['methods' => [Request::METHOD_POST]])]
    public function pruneOrphans(
        Request $request,
        OrphanFilePrunerInterface $orphanFilePruner,
        ReadableFilesizeExtension $filesizeExtension,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('prune_orphans', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('admin.flash.invalid_csrf'));

            $this->auditLogger->log(
                action: AuditAction::ORPHANS_PRUNE,
                channel: AuditChannel::ADMIN,
                success: false,
                metadata: ['reason' => 'invalid_csrf'],
            );

            return $this->redirectToRoute('admin_active_tasks');
        }

        $result = $orphanFilePruner->prune();

        $this->auditLogger->log(
            action: AuditAction::ORPHANS_PRUNE,
            channel: AuditChannel::ADMIN,
            metadata: ['deleted' => $result['deleted'], 'failed' => $result['failed'], 'bytes' => $result['bytes']],
        );

        if ($result['failed'] > 0) {
            $this->addFlash('error', $this->translator->trans('admin.flash.prune_failed', [
                '%deleted%' => $result['deleted'],
                '%failed%'  => $result['failed'],
            ]));
        } else {
            $this->addFlash('success', $this->translator->trans('admin.flash.pruned', [
                '%count%' => $result['deleted'],
                '%size%'  => $filesizeExtension->readableFilesize($result['bytes']),
            ]));
        }

        return $this->redirectToRoute('admin_active_tasks');
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('admin.dashboard.title'))
        ;
    }

    #[\Override]
    /**
     * @throws \Exception
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        if (!$user instanceof User) {
            throw new \Exception('Wrong user class');
        }

        return parent::configureUserMenu($user)
            ->setAvatarUrl($user->getAvatarUrl())
        ;
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute($this->translator->trans('admin.menu.active_tasks'), 'fa-solid fa-list-check', 'admin_active_tasks');
        yield MenuItem::linkTo(UserCrudController::class, $this->translator->trans('admin.menu.users'), 'fa fa-users');
        yield MenuItem::linkTo(SourceCrudController::class, $this->translator->trans('admin.menu.sources'), 'fa-regular fa-file-video');
        yield MenuItem::linkTo(LogCrudController::class, $this->translator->trans('admin.menu.logs'), 'fa-solid fa-book');
        yield MenuItem::linkTo(AuditEventCrudController::class, $this->translator->trans('admin.menu.audit'), 'fa-solid fa-clipboard-list');
        yield MenuItem::linkToUrl($this->translator->trans('admin.menu.back_to_downloads'), 'fa-solid fa-arrow-left', $this->generateUrl('ui_download_index'));
    }

    #[\Override]
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
