<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Source;
use App\Repository\SourceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SourceRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SourceRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = self::getContainer()->get(SourceRepositoryInterface::class);

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
     * Verifies a stored source is found by filename and missing names return null.
     */
    public function testFindOneByFilenameReturnsStoredSource(): void
    {
        $source = new Source()->setFilename('repo-test.mp4')
            ->setFilepath('/tmp')
            ->setSize(12.5)
        ;

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->assertSame($source, $this->repository->findOneByFilename('repo-test.mp4'));
        $this->assertNull($this->repository->findOneByFilename('missing.mp4'));
    }

    /**
     * Verifies the filename list used by the orphan pruner includes stored sources.
     */
    public function testFindAllFilenamesContainsStoredFilename(): void
    {
        $source = new Source()->setFilename('repo-list.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
        ;

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->assertContains('repo-list.mp4', $this->repository->findAllFilenames());
    }

    /**
     * Verifies findAllSources returns every persisted source.
     */
    public function testFindAllSourcesReturnsStoredSources(): void
    {
        $source = new Source()->setFilename('repo-all.mp4')
            ->setFilepath('/tmp')
            ->setSize(2.0)
        ;

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->assertContains($source, $this->repository->findAllSources());
    }
}
