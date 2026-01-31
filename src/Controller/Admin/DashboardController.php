<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Log;
use App\Entity\User;
use App\Entity\Source;
use App\Helper\Helper;
use Doctrine\DBAL\Exception;
use App\Repository\LogRepository;
use App\Repository\UserRepository;
use App\Service\QueueCounterService;
use App\Service\RabbitMQApiQueueService;
use App\Service\MessengerQueueCounterService;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use Symfony\Component\Security\Core\User\UserInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LogRepository $logRepository,
        private readonly MessengerQueueCounterService $messengerQueueCounterService,
        private readonly RabbitMQApiQueueService $rabbitMQApiQueueService,
    ) {
    }

    /**
     * @throws Exception
     */
    #[\Override]
    public function index(): Response
    {
        $totalUsers               = $this->userRepository->getTotalCount();
        $totalSuccessDownloads    = $this->logRepository->getTotalSuccessCount();
        $totalPendingDownloads    = $this->messengerQueueCounterService->getQueueCount();
        $totalInProgressDownloads = $this->rabbitMQApiQueueService->getProcessingMessagesCount();
        $totalErrorDownloads      = $this->logRepository->getTotalErrorCount();
        $totalSizeDownloaded      = $this->logRepository->getTotalSize();
        $maxSizeDownloaded        = $this->logRepository->getMaxSize();

        return $this->render('admin/index.html.twig', [
            'totalUsers'      => $totalUsers,
            'totalDownloads'  => $totalSuccessDownloads,
            'totalPending'    => $totalPendingDownloads,
            'totalInProgress' => $totalInProgressDownloads,
            'totalErrors'     => $totalErrorDownloads,
            'totalSize'       => Helper::formatBytes($totalSizeDownloaded),
            'maxSize'         => Helper::formatBytes($maxSizeDownloaded),
        ]);
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
        yield MenuItem::linkToUrl('Home page', 'fa fa-home', $this->generateUrl('ui_download_index'));
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
