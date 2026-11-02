<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\DashboardController;
use App\Entity\DownloadTask;
use App\Repository\UserRepository;
use App\Service\AuditLoggerInterface;
use App\Service\OrphanFilePrunerInterface;
use Doctrine\ORM\EntityManagerInterface;
use OskarStark\Twig\ReadableFilesizeExtension;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
        $this->entityManager  = $this->getContainer()->get(EntityManagerInterface::class);
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
     * Verifies the purge action deletes queued tasks and redirects with a success flash.
     */
    public function testPurgeQueueDeletesQueuedTasks(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://youtube.com/watch?v=purge-test')->setQuality('best');
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $token = $this->getAdminToken('purge-queue');

        $this->client->request(Request::METHOD_POST, '/admin/purge-queue', ['_token' => $token]);

        $this->assertResponseRedirects('/admin/active-tasks');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-success', 'Purged');

        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(DownloadTask::class, $task->getId()));
    }

    /**
     * Verifies the reset action moves processing tasks back to queued.
     */
    public function testResetStuckTasksMovesProcessingToQueued(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://youtube.com/watch?v=reset-test')
            ->setQuality('best')
            ->setStatus(DownloadTask::STATUS_PROCESSING)
        ;
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $token = $this->getAdminToken('reset-stuck-tasks');

        $this->client->request(Request::METHOD_POST, '/admin/reset-stuck-tasks', ['_token' => $token]);

        $this->assertResponseRedirects('/admin/active-tasks');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-success', 'Reset');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(DownloadTask::class, $task->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(DownloadTask::STATUS_QUEUED, $reloaded->getStatus());

        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    /**
     * Verifies the prune action deletes orphan files and redirects with a success flash.
     */
    public function testPruneOrphansDeletesOrphanFiles(): void
    {
        $projectDir   = (string) $this->getContainer()->getParameter('kernel.project_dir');
        $downloadsDir = \sprintf('%s/var/downloads', $projectDir);

        if (!\is_dir($downloadsDir)) {
            \mkdir($downloadsDir, 0775, true);
        }

        $orphanPath = \sprintf('%s/admin-prune-%s.part', $downloadsDir, \bin2hex(\random_bytes(4)));
        \file_put_contents($orphanPath, 'orphan');
        \touch($orphanPath, \time() - 7200);

        try {
            $user = $this->userRepository->findOneByEmail('admin@admin.local');
            $this->client->loginUser($user);

            $crawler = $this->client->request(Request::METHOD_GET, '/admin/active-tasks');
            $token   = $this->extractToken($crawler, 'form[action*="/admin/prune-orphans"] input[name="_token"]');

            $this->client->request(Request::METHOD_POST, '/admin/prune-orphans', ['_token' => $token]);

            $this->assertResponseRedirects('/admin/active-tasks');
            $this->client->followRedirect();
            $this->assertSelectorTextContains('div.alert-success', 'Pruned');

            $this->assertFileDoesNotExist($orphanPath);
        } finally {
            @\unlink($orphanPath);
        }
    }

    /**
     * Verifies the prune action reports failed deletions with an error flash.
     */
    public function testPruneOrphansReportsFailedDeletions(): void
    {
        $pruner = $this->createStub(OrphanFilePrunerInterface::class);
        $pruner->method('prune')->willReturn(['deleted' => 1, 'bytes' => 10, 'failed' => 1]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Deleted 1 orphan file(s), failed to delete 1 file(s).');

        $controller = new DashboardController($translator, $this->createStub(AuditLoggerInterface::class));

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $tokenManager->method('isTokenValid')->willReturn(true);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/admin/active-tasks');

        $container = new Container();
        $container->set('security.csrf.token_manager', $tokenManager);
        $container->set('router', $urlGenerator);
        $container->set('request_stack', $requestStack);
        $controller->setContainer($container);

        $response = $controller->pruneOrphans($request, $pruner, new ReadableFilesizeExtension());

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertTrue($session->getFlashBag()->has('error'));
    }

    /**
     * Verifies configuring the user menu rejects foreign user implementations.
     */
    public function testConfigureUserMenuRejectsForeignUser(): void
    {
        $controller = new DashboardController($this->createStub(TranslatorInterface::class), $this->createStub(AuditLoggerInterface::class));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Wrong user class');

        $controller->configureUserMenu($this->createStub(UserInterface::class));
    }

    /**
     * Verifies the purge action rejects a request without a valid CSRF token.
     */
    public function testPurgeQueueRejectsInvalidCsrfToken(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/purge-queue');

        $this->assertResponseRedirects('/admin/active-tasks');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'Invalid CSRF token.');
    }

    /**
     * Verifies the reset action rejects a request without a valid CSRF token.
     */
    public function testResetStuckTasksRejectsInvalidCsrfToken(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/admin/reset-stuck-tasks');

        $this->assertResponseRedirects('/admin/active-tasks');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'Invalid CSRF token.');
    }

    /**
     * Verifies the active tasks page shows a recently persisted download task.
     */
    public function testActiveTasksPageListsRecentTask(): void
    {
        $task = new DownloadTask();

        $task->setUrl('https://youtube.com/watch?v=active-tasks-test')->setQuality('best');
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/admin/active-tasks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'https://youtube.com/watch?v=active-tasks-test');

        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    private function getAdminToken(string $routePath): string
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/active-tasks');
        $this->assertResponseIsSuccessful();

        return $this->extractToken($crawler, \sprintf('form[action*="/admin/%s"] input[name="_token"]', $routePath));
    }

    private function extractToken(Crawler $crawler, string $selector): string
    {
        $input = $crawler->filter($selector);
        $this->assertCount(1, $input, \sprintf('CSRF token input "%s" was not found.', $selector));

        return (string) $input->attr('value');
    }
}
