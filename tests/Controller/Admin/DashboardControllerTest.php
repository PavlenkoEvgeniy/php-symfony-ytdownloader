<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\DownloadTask;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Verifies an anonymous visitor is redirected from /admin to the login page.
     */
    public function testAdminPageRedirectsToLoginForAnonymousUser(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/login');
    }

    /**
     * Verifies an admin user is redirected from /admin to the active tasks page.
     */
    public function testAdminPageRedirectsToActiveTasksForAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/admin/active-tasks');
    }

    /**
     * Verifies a regular user is redirected to UI downloads with an access denied warning.
     */
    public function testAdminPageRedirectsRegularUserToUiDownloadsWithWarningMessage(): void
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/admin');

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

        $this->client->request(Request::METHOD_GET, '/admin/user');

        $this->assertResponseIsSuccessful();
    }

    /**
     * Verifies an anonymous visitor is redirected from the active tasks page to login.
     */
    public function testActiveTasksPageRedirectsToLoginForAnonymousUser(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/active-tasks');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/login');
    }

    /**
     * Verifies a regular user is redirected away from the active tasks page.
     */
    public function testActiveTasksPageDeniedForRegularUser(): void
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/admin/active-tasks');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/ui/download');
    }

    /**
     * Verifies an admin user sees the active tasks page.
     */
    public function testActiveTasksPageRendersForAdminUser(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/admin/active-tasks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Active Tasks');
    }

    /**
     * Verifies the prune action rejects a request without a valid CSRF token.
     */
    public function testPruneOrphansRejectsInvalidCsrfToken(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/prune-orphans');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertResponseRedirects('/admin/active-tasks');

        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'Invalid CSRF token.');
    }

    /**
     * Verifies the active tasks page shows a recently persisted download task.
     */
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

        $this->client->request(Request::METHOD_GET, '/admin/active-tasks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'https://youtube.com/watch?v=active-tasks-test');

        $em->remove($task);
        $em->flush();
    }
}
