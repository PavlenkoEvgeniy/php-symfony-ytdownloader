<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Log;
use App\Entity\Source;
use App\Entity\User;
use App\Helper\Helper;
use App\Repository\LogRepository;
use App\Repository\UserRepository;
use App\Service\QueueCounterService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LogRepository $logRepository,
        private readonly QueueCounterService $queueCounter,
    ) {
    }

    #[\Override]
    public function index(): Response
    {
        $totalUsers = $this->userRepository->getTotalCount();

        $totalDownloads = $this->logRepository->getTotalSuccessCount();

        $totalPending = $this->queueCounter->getQueueCount();

        $totalInProgress = $this->logRepository->getTotalInProgressCount();

        $totalErrors = $this->logRepository->getTotalErrorCount();

        $totalSize = $this->logRepository->getTotalSize();

        $maxSize = $this->logRepository->getMaxSize();

        return $this->render('admin/index.html.twig', [
            'totalUsers'          => $totalUsers,
            'totalDownloads'      => $totalDownloads,
            'totalPending'        => $totalPending,
            'totalInProgress'     => $totalInProgress,
            'totalErrors'         => $totalErrors,
            'totalSize'           => Helper::formatBytes($totalSize),
            'maxSize'             => Helper::formatBytes($maxSize),
        ]);
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('YtDownloader Admin')
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
        yield MenuItem::linkToUrl('Home page', 'fa fa-home', $this->generateUrl('ui_youtube_download_index'));
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-dashboard');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Sources', 'fa-regular fa-file-video', Source::class);
        yield MenuItem::linkToCrud('Logs', 'fa-solid fa-book', Log::class);
        yield MenuItem::linkToLogout('Logout', 'fa fa-sign-out');
    }

    #[\Override]
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
