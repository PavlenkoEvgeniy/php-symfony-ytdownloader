<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V2;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Tests\Support\FakeDownloadsStreamWrapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SourceControllerTest extends WebTestCase
{
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
        $this->client                      = static::createClient();
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

        if ([] !== $this->createdSourceIds) {
            foreach ($this->createdSourceIds as $id) {
                $source = $this->sourceRepository->find($id);
                if ($source instanceof Source) {
                    $this->entityManager->remove($source);
                }
            }
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    /**
     * Verifies the source index returns items and a count for an authenticated user.
     */
    public function testIndexReturnsItems(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v2/source', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('count', $data);
    }

    /**
     * Verifies downloading a source returns the file as an attachment.
     */
    public function testDownloadReturnsFile(): void
    {
        $source = $this->createSourceWithFile('test-download-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, \sprintf('/api/v2/source/%s/download', $source->getId()), server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('content-disposition'));
        $this->assertStringContainsString('test-download-v2.mp4', (string) $this->client->getResponse()->headers->get('content-disposition'));
    }

    /**
     * Verifies deleting a source removes both the entity and the file.
     */
    public function testDeleteRemovesFileAndEntity(): void
    {
        $source = $this->createSourceWithFile('test-delete-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_DELETE, \sprintf('/api/v2/source/%s', $source->getId()), server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertNull($this->sourceRepository->find($source->getId()));
        $this->assertFileDoesNotExist(\sprintf('%s/%s', $source->getFilepath(), $source->getFilename()));
    }

    /**
     * Verifies the index serializes stored sources.
     */
    public function testIndexReturnsStoredSource(): void
    {
        $source = $this->createSourceWithFile('index-item-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v2/source?order=ASC', server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $filenames = \array_column($data['items'], 'filename', 'id');
        $this->assertArrayHasKey($source->getId(), $filenames);
        $this->assertSame('index-item-v2.mp4', $filenames[$source->getId()]);
    }

    /**
     * Verifies downloading a missing file returns HTTP 404.
     */
    public function testDownloadReturnsNotFoundForMissingFile(): void
    {
        $source = $this->createSource('missing-download-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, \sprintf('/api/v2/source/%s/download', $source->getId()), server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Verifies deleting a source whose file is missing returns HTTP 404.
     */
    public function testDeleteReturnsNotFoundForMissingFile(): void
    {
        $source = $this->createSource('missing-delete-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_DELETE, \sprintf('/api/v2/source/%s', $source->getId()), server: [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertNotNull($this->sourceRepository->find($source->getId()));
    }

    /**
     * Verifies a failure to remove the file returns HTTP 500 and keeps the entity.
     */
    public function testDeleteReturnsServerErrorWhenFileCannotBeRemoved(): void
    {
        FakeDownloadsStreamWrapper::register();

        try {
            $source = $this->createSource('orphan.part', FakeDownloadsStreamWrapper::url('readable'));
            $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

            $this->client->request(Request::METHOD_DELETE, \sprintf('/api/v2/source/%s', $source->getId()), server: [
                'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
            ]);

            $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->assertNotNull($this->sourceRepository->find($source->getId()));
        } finally {
            FakeDownloadsStreamWrapper::unregister();
        }
    }

    /**
     * Creates a source row without creating its file on disk.
     */
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

        $source = new Source();

        $source
            ->setFilename($filename)
            ->setFilepath($this->downloadsDir)
            ->setSize((float) \filesize($filePath));

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->createdSourceIds[] = (int) $source->getId();
        $this->createdFiles[]     = $filePath;

        return $source;
    }

    private function loginAndGetToken(string $email, string $password): string
    {
        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return (string) ($data['token'] ?? '');
    }
}
