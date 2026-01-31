<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V2;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SourceControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private SourceRepository $sourceRepository;
    private string $downloadsDir;
    /** @var list<int> */
    private array $createdSourceIds = [];
    /** @var list<string> */
    private array $createdFiles     = [];

    public function setUp(): void
    {
        $this->client           = static::createClient();
        $this->em               = $this->getContainer()->get(EntityManagerInterface::class);
        $this->sourceRepository = $this->getContainer()->get(SourceRepository::class);
        $this->downloadsDir     = \sprintf('%s/var/downloads', $this->getContainer()->getParameter('kernel.project_dir'));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (\is_file($file)) {
                @\unlink($file);
            }
        }

        if (!empty($this->createdSourceIds)) {
            foreach ($this->createdSourceIds as $id) {
                $source = $this->sourceRepository->find($id);
                if ($source) {
                    $this->em->remove($source);
                }
            }
            $this->em->flush();
        }

        parent::tearDown();
    }

    public function testIndexReturnsItems(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v2/source', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $data = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('count', $data);
    }

    public function testDownloadReturnsFile(): void
    {
        $source = $this->createSourceWithFile('test-download-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_GET, '/api/v2/source/' . $source->getId() . '/download', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('content-disposition'));
        $this->assertStringContainsString('test-download-v2.mp4', (string) $this->client->getResponse()->headers->get('content-disposition'));
    }

    public function testDeleteRemovesFileAndEntity(): void
    {
        $source = $this->createSourceWithFile('test-delete-v2.mp4');
        $token  = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->request(Request::METHOD_DELETE, '/api/v2/source/' . $source->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertNull($this->sourceRepository->find($source->getId()));
        $this->assertFileDoesNotExist($source->getFilepath() . '/' . $source->getFilename());
    }

    private function createSourceWithFile(string $filename): Source
    {
        if (!\is_dir($this->downloadsDir)) {
            \mkdir($this->downloadsDir, 0775, true);
        }

        $filePath = $this->downloadsDir . '/' . $filename;
        \file_put_contents($filePath, 'test');

        $source = new Source();
        $source
            ->setFilename($filename)
            ->setFilepath($this->downloadsDir)
            ->setSize((float) \filesize($filePath));

        $this->em->persist($source);
        $this->em->flush();

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
