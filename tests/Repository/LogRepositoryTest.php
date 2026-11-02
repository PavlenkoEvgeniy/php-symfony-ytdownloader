<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Log;
use App\Repository\LogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private LogRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = self::getContainer()->get(LogRepository::class);

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
     * Verifies the repository returns persisted log entries.
     */
    public function testFindAllReturnsStoredLog(): void
    {
        $log = new Log();
        $log->setType('success')->setMessage('Repository test log');

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->assertContains($log, $this->repository->findAll());
    }
}
