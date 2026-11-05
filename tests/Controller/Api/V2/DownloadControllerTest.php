<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V2;

use App\Repository\DownloadSettingsRepositoryInterface;
use App\Repository\DownloadTaskRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private DownloadSettingsRepositoryInterface $settingsRepository;
    private DownloadTaskRepositoryInterface $downloadTaskRepository;
    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        $this->client                 = static::createClient();
        $this->settingsRepository     = $this->getContainer()->get(DownloadSettingsRepositoryInterface::class);
        $this->downloadTaskRepository = $this->getContainer()->get(DownloadTaskRepositoryInterface::class);
        $this->entityManager          = $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Verifies the create endpoint rejects unauthenticated requests.
     */
    public function testCreateRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/v2/download/create');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Verifies an invalid download URL is rejected with HTTP 422.
     */
    public function testCreateValidatesPayload(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/download/create', [
            'url'     => 'not-a-url',
            'quality' => 'best',
        ], [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Verifies a valid download request is accepted with HTTP 202.
     */
    public function testCreateAcceptsValidRequest(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/download/create', [
            'url'     => 'https://test.local',
            'quality' => 'moderate',
        ], [
            'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
    }

    /**
     * Verifies the create endpoint is forbidden and creates no task when the web intake is off.
     */
    public function testCreateIsForbiddenWhenWebIntakeDisabled(): void
    {
        $token = $this->loginAndGetToken('admin@admin.local', 'admin123456');

        $this->setWebIntakeEnabled(false);

        try {
            $before = $this->countTasks();

            $this->client->jsonRequest(Request::METHOD_POST, '/api/v2/download/create', [
                'url'     => 'https://test.local',
                'quality' => 'moderate',
            ], [
                'HTTP_AUTHORIZATION' => \sprintf('Bearer %s', $token),
            ]);

            $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

            $response = $this->client->getResponse();
            $this->assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));

            $data = \json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(Response::HTTP_FORBIDDEN, $data['status']);
            $this->assertSame('New downloads through the web are disabled.', $data['detail']);
            $this->assertSame($before, $this->countTasks());
        } finally {
            $this->setWebIntakeEnabled(true);
        }
    }

    private function setWebIntakeEnabled(bool $enabled): void
    {
        $settings = $this->settingsRepository->getSettings();
        $settings->setWebIntakeEnabled($enabled);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    private function countTasks(): int
    {
        return \array_sum($this->downloadTaskRepository->getStatusCounts());
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
