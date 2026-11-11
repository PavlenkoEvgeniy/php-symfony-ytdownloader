<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\DownloadTask;
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

    /**
     * Verifies old sources are returned, fresh ones are not, and sources of
     * active tasks are skipped unless explicitly requested.
     */
    public function testFindOlderThanSkipsFreshAndActiveSources(): void
    {
        $old = (new Source())->setFilename('repo-old.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
            ->setCreatedAt(new \DateTime('-2 days'))
        ;

        $fresh = (new Source())->setFilename('repo-fresh.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
        ;

        $task = (new DownloadTask())->setUrl('https://test.local')->setQuality('best');

        $active = (new Source())->setFilename('repo-active.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
            ->setCreatedAt(new \DateTime('-2 days'))
            ->setDownloadTask($task)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->persist($old);
        $this->entityManager->persist($fresh);
        $this->entityManager->persist($active);
        $this->entityManager->flush();

        $cutoff = new \DateTimeImmutable('-1 day');
        $found  = $this->repository->findOlderThan($cutoff);

        $this->assertContains($old, $found);
        $this->assertNotContains($fresh, $found);
        $this->assertNotContains($active, $found);
        $this->assertContains($active, $this->repository->findOlderThan($cutoff, false));
    }

    /**
     * Verifies only the sources of the requested task are returned, ordered by id.
     */
    public function testFindByDownloadTaskReturnsItsSourcesInOrder(): void
    {
        $task = new DownloadTask()->setUrl('https://test.local/repo-task')
            ->setQuality('best')
        ;
        $other = new DownloadTask()->setUrl('https://test.local/repo-other')
            ->setQuality('best')
        ;

        $first = new Source()->setFilename('repo-task-1.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
            ->setDownloadTask($task)
        ;
        $second = new Source()->setFilename('repo-task-2.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
            ->setDownloadTask($task)
        ;
        $foreign = new Source()->setFilename('repo-other-1.mp4')
            ->setFilepath('/tmp')
            ->setSize(1.0)
            ->setDownloadTask($other)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->persist($other);
        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->persist($foreign);
        $this->entityManager->flush();

        $found = $this->repository->findByDownloadTask($task);

        $this->assertSame([$first, $second], $found);
        $this->assertNotContains($foreign, $found);
    }
}
