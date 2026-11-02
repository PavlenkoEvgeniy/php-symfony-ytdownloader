<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LogManager implements LogManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function create(string $type, string $message, ?float $size = null): Log
    {
        $log = new Log()->setType($type)
            ->setMessage($message)
        ;

        if (null !== $size) {
            $log->setSize($size);
        }

        $this->entityManager->persist($log);

        return $log;
    }

    #[\Override]
    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
