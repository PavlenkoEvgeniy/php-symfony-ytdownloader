<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AuditEvent;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use App\Repository\AuditEventRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AuditEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AuditEventRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = self::getContainer()->get(AuditEventRepositoryInterface::class);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollback();
            $this->entityManager->clear();
        }
    }

    /**
     * Verifies only events older than the cutoff are counted and deleted.
     */
    public function testCountsAndDeletesOnlyOldEvents(): void
    {
        $old = new AuditEvent(
            occurredAt: new \DateTimeImmutable('-10 days'),
            action: AuditAction::LOGIN,
            channel: AuditChannel::UI,
            actorType: AuditActorType::USER,
            success: true,
        );
        $new = new AuditEvent(
            occurredAt: new \DateTimeImmutable(),
            action: AuditAction::LOGOUT,
            channel: AuditChannel::UI,
            actorType: AuditActorType::USER,
            success: true,
        );

        $this->entityManager->persist($old);
        $this->entityManager->persist($new);
        $this->entityManager->flush();

        $cutoff = new \DateTimeImmutable('-5 days');

        $this->assertSame(1, $this->repository->countOlderThan($cutoff));
        $this->assertSame(1, $this->repository->deleteOlderThan($cutoff));
        $this->assertSame(0, $this->repository->countOlderThan($cutoff));
    }
}
