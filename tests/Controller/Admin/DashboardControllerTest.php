<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\DownloadTask;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DashboardControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testAdminPageRedirectsToLoginForAnonymousUser(): void
    {
        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/login');
    }

    public function testAdminPageRedirectsToUserCrudForAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/admin/user');
    }

    public function testAdminPageRedirectsRegularUserToUiDownloadsWithWarningMessage(): void
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/ui/download');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('div.alert-warning', 'Access denied.');
    }

    /**
     * Regression test: the admin menu is rendered on every CRUD page. A menu item
     * configured with a wrong target (e.g. the pre-4.29 MenuItem::linkTo() argument
     * order) throws during rendering of @EasyAdmin/menu.html.twig instead of
     * returning a successful page.
     */
    public function testAdminCrudPageWithMenuRendersForAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/user');

        $this->assertResponseIsSuccessful();
    }

    public function testActiveTasksPageRedirectsToLoginForAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/active-tasks');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/login');
    }

    public function testActiveTasksPageDeniedForRegularUser(): void
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/active-tasks');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/ui/download');
    }

    public function testActiveTasksPageRendersForAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/active-tasks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Active Tasks');
    }

    public function testActiveTasksPageListsRecentTask(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine')->getManager();

        $task = new DownloadTask();
        $task->setUrl('https://youtube.com/watch?v=active-tasks-test')->setQuality('best');
        $em->persist($task);
        $em->flush();

        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/active-tasks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'https://youtube.com/watch?v=active-tasks-test');

        $em->remove($task);
        $em->flush();
    }
}
