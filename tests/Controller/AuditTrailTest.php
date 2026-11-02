<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditEvent;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuditTrailTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private string $downloadsDir;
    /** @var string[] */
    private array $createdFiles   = [];
    private ?string $createdEmail = null;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->entityManager  = $this->getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
        $this->passwordHasher = $this->getContainer()->get(UserPasswordHasherInterface::class);
        $this->downloadsDir   = \sprintf('%s/var/downloads', $this->getContainer()->getParameter('kernel.project_dir'));

        $this->entityManager->getConnection()->executeStatement('DELETE FROM audit_event');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @\unlink($file);
        }

        if (null !== $this->createdEmail) {
            $created = $this->userRepository->findOneByEmail($this->createdEmail);

            if ($created instanceof User) {
                $this->entityManager->remove($created);
                $this->entityManager->flush();
            }
        }

        $this->entityManager->getConnection()->executeStatement('DELETE FROM audit_event');

        parent::tearDown();
    }

    /**
     * Verifies a successful API login is recorded with the user as the actor.
     */
    public function testLoginIsAudited(): void
    {
        $email              = 'audit-login@test.local';
        $this->createdEmail = $email;

        $user = new User();

        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
            ->setRoles(['ROLE_USER'])
        ;

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->jsonRequest(Request::METHOD_POST, '/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'Password123!',
        ]);
        $this->assertResponseIsSuccessful();

        $event = $this->findEvent(AuditAction::LOGIN, $email);

        $this->assertSame('api_v1', $event->getChannel());
        $this->assertSame('user', $event->getActorType());
        $this->assertSame($user->getId(), $event->getActorId());
        $this->assertTrue($event->isSuccess());
    }

    /**
     * Verifies a UI download request is recorded with its actor and source URL.
     */
    public function testDownloadRequestIsAudited(): void
    {
        $url = 'https://www.youtube.com/watch?v=audit-trail-test';
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/download');
        $form    = $crawler->filter('form')->form([
            'download_form[link]'    => $url,
            'download_form[quality]' => 'best',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/ui/source');

        $event = $this->findEvent(AuditAction::DOWNLOAD_REQUEST, $url);
        $this->assertSame('ui', $event->getChannel());
        $this->assertSame('admin@admin.local', $event->getActorLabel());
        $this->assertSame(['quality' => 'best'], $event->getMetadata());

        $this->entityManager->createQuery('DELETE App\Entity\DownloadTask t WHERE t.url = :url')
            ->setParameter('url', $url)
            ->execute()
        ;
    }

    /**
     * Verifies deleting a source is recorded with its filename and actor.
     */
    public function testSourceDeleteIsAudited(): void
    {
        $source = $this->createSourceWithFile('audit-delete.mp4');
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/ui/source');
        $token   = (string) $crawler->filter(\sprintf('form[action="/ui/source/delete/%d"] input[name="_token"]', $source->getId()))->attr('value');

        $this->client->request(Request::METHOD_POST, \sprintf('/ui/source/delete/%d', $source->getId()), [
            '_token' => $token,
        ]);
        $this->assertResponseRedirects('/ui/source');

        $event = $this->findEvent(AuditAction::SOURCE_DELETE, 'audit-delete.mp4');
        $this->assertSame('ui', $event->getChannel());
        $this->assertSame('admin@admin.local', $event->getActorLabel());
        $this->assertTrue($event->isSuccess());
        $this->assertSame($source->getId(), $event->getTargetId());
    }

    private function loginAsAdmin(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);
    }

    private function findEvent(AuditAction $action, string $targetLabel): AuditEvent
    {
        $events = $this->entityManager->getRepository(AuditEvent::class)->findBy([], ['id' => 'DESC'], 20);

        foreach ($events as $event) {
            if ($event->getAction() === $action->value && $event->getTargetLabel() === $targetLabel) {
                return $event;
            }
        }

        $this->fail(\sprintf('Audit event "%s" for "%s" was not recorded.', $action->value, $targetLabel));
    }

    private function createSourceWithFile(string $filename): Source
    {
        if (!\is_dir($this->downloadsDir)) {
            \mkdir($this->downloadsDir, 0775, true);
        }

        $filePath = \sprintf('%s/%s', $this->downloadsDir, $filename);
        \file_put_contents($filePath, 'test');
        $this->createdFiles[] = $filePath;

        $source = new Source();

        $source->setFilename($filename)
            ->setFilepath($this->downloadsDir)
            ->setSize((float) \filesize($filePath))
        ;

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        return $source;
    }
}
