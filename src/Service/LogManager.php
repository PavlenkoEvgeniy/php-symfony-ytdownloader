<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LogManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function create(string $type, string $message, ?float $size = null): Log
    {
        $log = new Log();
        $log
            ->setType($type)
            ->setMessage($message);

        if (null !== $size) {
            $log->setSize($size);
        }

        $this->em->persist($log);

        return $log;
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
