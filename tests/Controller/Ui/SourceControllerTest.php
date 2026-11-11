<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Repository\UserRepository;
use App\Tests\Support\FakeDownloadsStreamWrapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class SourceControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private SourceRepository $sourceRepository;
    private string $downloadsDir;
    /** @var int[] */
    private array $createdSourceIds = [];
    /** @var string[] */
    private array $createdFiles     = [];

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->userRepository              = $this->getContainer()->get(UserRepository::class);
        $this->entityManager               = $this->getContainer()->get(EntityManagerInterface::class);
        $this->sourceRepository            = $this->getContainer()->get(SourceRepository::class);
        $this->downloadsDir                = \sprintf('%s/var/downloads', $this->getContainer()->getParameter('kernel.project_dir'));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (\is_file($file)) {
                @\unlink($file);
            }
        }

        foreach ($this->createdSourceIds as $id) {
            $source = $this->sourceRepository->find($id);
            if ($source instanceof Source) {
                $this->entityManager->remove($source);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    /**
     * Verifies the sources page opens for an authenticated admin.
     */
    public function testSourcePageIsOpeningOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/source');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a.btn-gradient[href="/ui/download"]', '+ Add new download');
    }

    /**
     * Verifies the queue stats endpoint redirects anonymous visitors to login.
     */
    public function testQueueStatsRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/ui/source/queue-stats');
        $this->assertResponseRedirects();
    }

    /**
     * Verifies the queue stats endpoint returns status counters in the response body.
     */
    public function testQueueStatsReturnsCounters(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/source/queue-stats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $content = $this->client->getResponse()->getContent() ?: '{}';
        $data    = \json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('queued', $data);
        $this->assertArrayHasKey('processing', $data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayNotHasKey('tasks', $data);
    }

    /**
     * Regression test for the move of the active tasks table to the admin panel:
     * the UI page must not render the table for admins anymore, the admin panel owns it now.
     */
    public function testActiveTasksTableIsNotRenderedInUi(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/ui/source');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-tasks-body]');
        $this->assertSelectorTextNotContains('body', 'Active tasks');
    }

    /**
     * Verifies sorting submits on selection and downloads bypass Turbo.
     */
    public function testSortSelectAutoSubmitsAndDownloadsBypassTurbo(): void
    {
        $source = $this->createSourceWithFile('ui-markup.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/source');
        $this->assertResponseIsSuccessful();

        $select = $crawler->filter('select[name="order"]');
        $this->assertCount(1, $select);
        $this->assertSame('this.form.requestSubmit()', $select->attr('onchange'));
        $this->assertCount(0, $crawler->filter('select[name="order"] ~ button'));

        $download = $crawler->filter(\sprintf('a[href="/ui/source/download/%d"]', $source->getId()));
        $this->assertCount(1, $download);
        $this->assertSame('false', $download->attr('data-turbo'));
    }

    /**
     * Verifies the source details page renders the stored source.
     */
    public function testShowPageRendersSource(): void
    {
        $source = $this->createSourceWithFile('show-page.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d', $source->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'show-page.mp4');
    }

    /**
     * Verifies the file name links to the Source details page.
     */
    public function testFileNameLinksToShowPage(): void
    {
        $source = $this->createSourceWithFile('file-name-link.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/source');

        $this->assertResponseIsSuccessful();

        $link = $crawler->filter(\sprintf('a.file-name[href="/ui/source/%d"]', $source->getId()));

        $this->assertCount(1, $link);
        $this->assertSame('file-name-link.mp4', \trim((string) $link->text()));
        $this->assertStringContainsString('stretched-link', (string) $link->attr('class'));

        $row = $link->closest('tr');

        $this->assertInstanceOf(Crawler::class, $row);
        $this->assertStringContainsString('position-relative', (string) $row->attr('class'));
    }

    /**
     * Verifies the details page offers a confirmed delete action.
     */
    public function testShowPageOffersConfirmedDelete(): void
    {
        $source = $this->createSourceWithFile('show-delete.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d', $source->getId()));

        $this->assertResponseIsSuccessful();

        $formSelector = \sprintf('form[action="/ui/source/delete/%d"]', $source->getId());
        $form         = $crawler->filter($formSelector);

        $this->assertCount(1, $form);
        $this->assertStringContainsString('confirm(', (string) $form->attr('onsubmit'));
        $this->assertSelectorExists($formSelector . ' input[name="_token"]');
        $this->assertSelectorExists($formSelector . ' button[type="submit"]');
        $this->assertSelectorTextContains($formSelector . ' button', 'Delete');
    }

    /**
     * Verifies downloading a file requires authentication.
     */
    public function testDownloadRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/ui/source/download/1');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Verifies downloading a source returns the file as an attachment.
     */
    public function testDownloadReturnsFile(): void
    {
        $source = $this->createSourceWithFile('ui-download.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/download/%d', $source->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('content-disposition'));
    }

    /**
     * Verifies downloading a missing file redirects back with an error flash.
     */
    public function testDownloadRedirectsWhenFileIsMissing(): void
    {
        $source = $this->createSource('ui-missing-download.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/download/%d', $source->getId()));

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'An error occurred while downloading the file');
    }

    /**
     * Verifies a delete request with an invalid CSRF token is ignored.
     */
    public function testDeleteRejectsInvalidCsrfToken(): void
    {
        $source = $this->createSourceWithFile('csrf-delete.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_POST, \sprintf('/ui/source/delete/%d', $source->getId()));

        $this->assertResponseRedirects('/ui/source');
        $this->assertNotNull($this->sourceRepository->find($source->getId()));
    }

    /**
     * Verifies a delete request removes the file and the entity.
     */
    public function testDeleteRemovesFileAndSource(): void
    {
        $source = $this->createSourceWithFile('ui-delete.mp4');
        $this->loginAsAdmin();

        $token = $this->getSourceDeleteToken($source);

        $this->client->request(Request::METHOD_POST, \sprintf('/ui/source/delete/%d', $source->getId()), [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/ui/source');
        $this->assertFileDoesNotExist(\sprintf('%s/ui-delete.mp4', $this->downloadsDir));
        $this->assertNull($this->sourceRepository->find($source->getId()));
    }

    /**
     * Verifies deleting a source whose file is missing redirects with an error flash.
     */
    public function testDeleteRedirectsWhenFileIsMissing(): void
    {
        $source = $this->createSource('ui-missing-delete.mp4');
        $this->loginAsAdmin();

        $token = $this->getSourceDeleteToken($source);

        $this->client->request(Request::METHOD_POST, \sprintf('/ui/source/delete/%d', $source->getId()), [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/ui/source');
        $this->assertNotNull($this->sourceRepository->find($source->getId()));

        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'An error occurred while deleting the file');
    }

    /**
     * Verifies a failing unlink redirects with an error and keeps the entity.
     */
    public function testDeleteHandlesUnlinkFailure(): void
    {
        FakeDownloadsStreamWrapper::register();

        try {
            $source = $this->createSource('orphan.part', FakeDownloadsStreamWrapper::url('readable'));
            $this->loginAsAdmin();

            $token = $this->getSourceDeleteToken($source);

            $this->client->request(Request::METHOD_POST, \sprintf('/ui/source/delete/%d', $source->getId()), [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/ui/source');
            $this->assertNotNull($this->sourceRepository->find($source->getId()));
        } finally {
            FakeDownloadsStreamWrapper::unregister();
        }
    }

    /**
     * Verifies delete-all reports that there is nothing to delete.
     */
    public function testDeleteAllReportsNothingToDelete(): void
    {
        $this->createSourceWithFile('delete-all-token-source.mp4');
        $this->loginAsAdmin();
        $token = $this->getDeleteAllToken();

        foreach ($this->sourceRepository->findAllSources() as $existingSource) {
            $this->entityManager->remove($existingSource);
        }
        $this->entityManager->flush();
        @\unlink(\sprintf('%s/delete-all-token-source.mp4', $this->downloadsDir));
        $this->assertSame([], $this->sourceRepository->findAllSources());

        $this->client->request(Request::METHOD_POST, '/ui/source/delete-all', ['_token' => $token]);

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'Nothing to delete');
    }

    /**
     * Verifies delete-all rejects a missing CSRF token.
     */
    public function testDeleteAllRejectsInvalidCsrfToken(): void
    {
        $source = $this->createSourceWithFile('csrf-delete-all.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_POST, '/ui/source/delete-all');

        $this->assertResponseRedirects('/ui/source');
        $this->assertNotNull($this->sourceRepository->find($source->getId()));
    }

    /**
     * Verifies delete-all removes every file and reports the plural message.
     */
    public function testDeleteAllRemovesEverySource(): void
    {
        $first  = $this->createSourceWithFile('ui-delete-all-1.mp4');
        $second = $this->createSourceWithFile('ui-delete-all-2.mp4');
        $this->loginAsAdmin();

        $token = $this->getDeleteAllToken();

        $this->client->request(Request::METHOD_POST, '/ui/source/delete-all', ['_token' => $token]);

        $this->assertResponseRedirects('/ui/source');
        $this->assertNull($this->sourceRepository->find($first->getId()));
        $this->assertNull($this->sourceRepository->find($second->getId()));
        $this->assertFileDoesNotExist(\sprintf('%s/ui-delete-all-1.mp4', $this->downloadsDir));
        $this->assertFileDoesNotExist(\sprintf('%s/ui-delete-all-2.mp4', $this->downloadsDir));

        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-success', 'files were deleted');
    }

    /**
     * Verifies delete-all reports a single deleted file with the singular message.
     */
    public function testDeleteAllReportsSingleDeletedFile(): void
    {
        $first = $this->createSourceWithFile('ui-delete-single.mp4');

        foreach ($this->sourceRepository->findAllSources() as $source) {
            if ($source->getId() !== $first->getId()) {
                $this->entityManager->remove($source);
            }
        }
        $this->entityManager->flush();

        $this->loginAsAdmin();
        $token = $this->getDeleteAllToken();

        $this->client->request(Request::METHOD_POST, '/ui/source/delete-all', ['_token' => $token]);

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-success', '1 file was deleted');
    }

    /**
     * Verifies delete-all stops and reports an error when a file is missing.
     */
    public function testDeleteAllRedirectsWhenFileIsMissing(): void
    {
        $source = $this->createSourceWithFile('ui-delete-all-missing.mp4');
        $this->loginAsAdmin();

        $token = $this->getDeleteAllToken();
        @\unlink(\sprintf('%s/ui-delete-all-missing.mp4', $this->downloadsDir));

        $this->client->request(Request::METHOD_POST, '/ui/source/delete-all', ['_token' => $token]);

        $this->assertResponseRedirects('/ui/source');
        $this->assertNotNull($this->sourceRepository->find($source->getId()));

        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'the file does not exist');
    }

    /**
     * Verifies delete-all logs a failing unlink but still removes the entity.
     */
    public function testDeleteAllHandlesUnlinkFailure(): void
    {
        FakeDownloadsStreamWrapper::register();

        try {
            $source = $this->createSource('orphan.part', FakeDownloadsStreamWrapper::url('readable'));
            $this->loginAsAdmin();

            $token = $this->getDeleteAllToken();

            $this->client->request(Request::METHOD_POST, '/ui/source/delete-all', ['_token' => $token]);

            $this->assertResponseRedirects('/ui/source');
            $this->assertNull($this->sourceRepository->find($source->getId()));
        } finally {
            FakeDownloadsStreamWrapper::unregister();
        }
    }

    /**
     * Verifies the edit page renders the form for an admin.
     */
    public function testEditPageRendersForm(): void
    {
        $source = $this->createSourceWithFile('edit-page.mp4');
        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d/edit', $source->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="source_form[filename]"]');
    }

    /**
     * Verifies editing the filename renames the file on disk.
     */
    public function testEditRenamesFile(): void
    {
        $source = $this->createSourceWithFile('edit-old.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d/edit', $source->getId()));
        $form    = $crawler->filter('form')->form([
            'source_form[filename]' => 'edit-new.mp4',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/ui/source');
        $this->assertFileExists(\sprintf('%s/edit-new.mp4', $this->downloadsDir));
        $this->assertFileDoesNotExist(\sprintf('%s/edit-old.mp4', $this->downloadsDir));

        $this->entityManager->clear();
        $updated = $this->sourceRepository->find($source->getId());
        $this->assertNotNull($updated);
        $this->assertSame('edit-new.mp4', $updated->getFilename());

        $this->createdFiles[] = \sprintf('%s/edit-new.mp4', $this->downloadsDir);
    }

    /**
     * Verifies editing redirects with an error when the original file is missing.
     */
    public function testEditRedirectsWhenOriginalFileIsMissing(): void
    {
        $source = $this->createSource('edit-missing.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d/edit', $source->getId()));
        $form    = $crawler->filter('form')->form([
            'source_form[filename]' => 'edit-renamed.mp4',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'An error occurred while editing the file');
    }

    /**
     * Verifies a failing rename redirects with an error.
     */
    public function testEditRedirectsWhenRenameFails(): void
    {
        $source     = $this->createSourceWithFile('edit-rename-fail.mp4');
        $targetPath = \sprintf('%s/rename-target.mp4', $this->downloadsDir);
        \mkdir($targetPath);

        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d/edit', $source->getId()));
        $form    = $crawler->filter('form')->form([
            'source_form[filename]' => 'rename-target.mp4',
        ]);

        \set_error_handler(static function (int $severity, string $message): bool {
            if (\str_contains($message, 'rename(')) {
                throw new \ErrorException($message);
            }

            return false;
        });

        try {
            $this->client->submit($form);
        } finally {
            \restore_error_handler();
            @\rmdir($targetPath);
        }

        $this->assertResponseRedirects('/ui/source');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('div.alert-danger', 'An error occurred while renaming the file');
    }

    /**
     * Verifies an invalid filename shows a readable message, not a translation key.
     */
    public function testEditRejectsPathTraversalFilename(): void
    {
        $source = $this->createSourceWithFile('edit-traversal.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/ui/source/%d/edit', $source->getId()));
        $form    = $crawler->filter('form')->form([
            'source_form[filename]' => '../escape.mp4',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Filename must not contain slashes or be a dot path.');
        $this->assertSelectorTextNotContains('body', 'form.filename.invalid');

        $this->entityManager->clear();
        $this->assertSame('edit-traversal.mp4', $this->sourceRepository->find($source->getId())?->getFilename());
    }

    private function loginAsAdmin(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);
    }

    private function getSourceDeleteToken(Source $source): string
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/ui/source');

        return $this->extractToken($crawler, \sprintf('form[action="/ui/source/delete/%d"] input[name="_token"]', $source->getId()));
    }

    private function getDeleteAllToken(): string
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/ui/source');

        return $this->extractToken($crawler, 'form[action="/ui/source/delete-all"] input[name="_token"]');
    }

    private function extractToken(Crawler $crawler, string $selector): string
    {
        $input = $crawler->filter($selector);
        $this->assertCount(1, $input, \sprintf('CSRF token input "%s" was not found.', $selector));

        return (string) $input->attr('value');
    }

    private function createSource(string $filename, ?string $filepath = null): Source
    {
        $source = new Source();

        $source
            ->setFilename($filename)
            ->setFilepath($filepath ?? $this->downloadsDir)
            ->setSize(0.0);

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->createdSourceIds[] = (int) $source->getId();

        return $source;
    }

    private function createSourceWithFile(string $filename): Source
    {
        if (!\is_dir($this->downloadsDir)) {
            \mkdir($this->downloadsDir, 0775, true);
        }

        $filePath = \sprintf('%s/%s', $this->downloadsDir, $filename);
        \file_put_contents($filePath, 'test');

        $source = $this->createSource($filename);
        $source->setSize((float) \filesize($filePath));

        $this->createdFiles[] = $filePath;

        return $source;
    }
}
