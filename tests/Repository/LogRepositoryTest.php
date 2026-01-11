<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Log;
use App\Repository\LogRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LogRepositoryTest extends KernelTestCase
{
    private EntityManager $em;
    private LogRepository $logRepository;

    public function setUp(): void
    {
        // Skip functional DB tests if the database host is not resolvable in this environment
        $dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? \getenv('DATABASE_URL') ?: '';
        $parts = \parse_url($dbUrl);
        if (false !== $parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (\gethostbyname($host) === $host) {
                $this->markTestSkipped('Database host not resolvable - skipping LogRepository tests.');
            }
        }

        self::bootKernel();

        /** @var EntityManager $em */
        $em                  = self::getContainer()->get('doctrine')->getManager();
        $this->em            = $em;
        $this->logRepository = $this->em->getRepository(Log::class);

        // Begin transaction so we can rollback after test to keep DB clean
        $this->em->getConnection()->beginTransaction();
    }

    public function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
            $this->em->clear();
        }

        parent::tearDown();
    }

    public function testCountsAndSizesAreCalculatedCorrectly(): void
    {
        // Create entries with different types and sizes
        $logsData = [
            ['type' => 'processing', 'size' => null],
            ['type' => 'in progress', 'size' => null],
            ['type' => 'in progress', 'size' => null],
            ['type' => 'success', 'size' => 10.5],
            ['type' => 'success', 'size' => 20.0],
            ['type' => 'success', 'size' => 4.4],
            ['type' => 'error', 'size' => 2.1],
        ];

        foreach ($logsData as $data) {
            $log = new Log();
            $log->setType($data['type']);
            $log->setMessage('test');
            if (null !== $data['size']) {
                $log->setSize($data['size']);
            }

            $this->em->persist($log);
        }

        $this->em->flush();

        // Assert counts
        $this->assertSame(1, $this->logRepository->getTotalProcessingCount());
        $this->assertSame(2, $this->logRepository->getTotalInProgressCount());
        $this->assertSame(3, $this->logRepository->getTotalSuccessCount());
        $this->assertSame(1, $this->logRepository->getTotalErrorCount());

        // Assert sizes (note repository casts totals to int)
        $expectedTotal = (int) (10.5 + 20.0 + 4.4 + 2.1); // 36 (int cast)
        $this->assertSame($expectedTotal, $this->logRepository->getTotalSize());

        $this->assertSame(20, $this->logRepository->getMaxSize());
    }
}
