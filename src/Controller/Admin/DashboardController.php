<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\DownloadTaskRepository;
use App\Service\QueuePurgeService;
use App\Service\QueueStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    #[\Override]
    public function index(): RedirectResponse
    {
        return $this->redirect(
            $this->container->get(AdminUrlGeneratorInterface::class)
                ->setController(UserCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    /**
     * Display-only overview of the download queue: counters plus the recent active tasks.
     */
    #[AdminRoute(path: '/active-tasks', name: 'active_tasks')]
    public function activeTasks(
        QueueStatsService $queueStatsService,
        DownloadTaskRepository $downloadTaskRepository,
    ): Response {
        return $this->render('admin/active_tasks.html.twig', [
            'stats' => $queueStatsService->getStats(),
            'tasks' => $downloadTaskRepository->getRecentActiveTasks(),
        ]);
    }

    /**
     * Purge the queue: delete every queued task together with all pending transport messages.
     */
    #[AdminRoute(path: '/purge-queue', name: 'purge_queue', options: ['methods' => ['POST']])]
    public function purgeQueue(Request $request, QueuePurgeService $queuePurgeService): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('purge_queue', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_active_tasks');
        }

        $result = $queuePurgeService->purge();

        $this->addFlash(
            'success',
            \sprintf('Purged %d task(s) and %d pending message(s).', $result['tasks'], $result['messages']),
        );

        return $this->redirectToRoute('admin_active_tasks');
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Admin Panel')
        ;
    }

    /**
     * @throws \Exception
     */
    #[\Override]
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
        yield MenuItem::linkToRoute('Active Tasks', 'fa-solid fa-list-check', 'admin_active_tasks');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(SourceCrudController::class, 'Sources', 'fa-regular fa-file-video');
        yield MenuItem::linkTo(LogCrudController::class, 'Logs', 'fa-solid fa-book');
        yield MenuItem::linkToUrl('Back to downloads', 'fa-solid fa-arrow-left', $this->generateUrl('ui_download_index'));
    }

    #[\Override]
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
