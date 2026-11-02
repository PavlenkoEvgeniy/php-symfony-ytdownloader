<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\AuditEvent;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AuditEventCrudControllerFunctionalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->entityManager  = $this->getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    /**
     * Verifies the audit log list and detail pages render for an admin.
     */
    public function testAuditLogRendersForAdmin(): void
    {
        $event = new AuditEvent(
            occurredAt: new \DateTimeImmutable(),
            action: AuditAction::SOURCE_DELETE,
            channel: AuditChannel::UI,
            actorType: AuditActorType::USER,
            success: true,
            actorLabel: 'admin@admin.local',
            targetType: 'source',
            targetId: 7,
            targetLabel: 'rendered-video.mp4',
            ip: '192.0.2.1',
            metadata: ['reason' => 'test'],
        );

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        try {
            $user = $this->userRepository->findOneByEmail('admin@admin.local');
            $this->client->loginUser($user);

            $this->client->request(Request::METHOD_GET, '/admin/audit-event');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', 'rendered-video.mp4');
            $this->assertSelectorTextContains('body', 'File deleted');

            $this->client->request(Request::METHOD_GET, \sprintf('/admin/audit-event/%d', $event->getId()));
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', 'rendered-video.mp4');
            $this->assertSelectorTextContains('body', 'test');
        } finally {
            $this->entityManager->clear();

            $managed = $this->entityManager->find(AuditEvent::class, $event->getId());

            if ($managed instanceof AuditEvent) {
                $this->entityManager->remove($managed);
                $this->entityManager->flush();
            }
        }
    }
}
