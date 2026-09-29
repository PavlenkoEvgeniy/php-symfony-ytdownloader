<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DownloadMetricRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DownloadMetricRepository::class)]
class DownloadMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $totalBytes = 0.0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTotalBytes(): ?float
    {
        return $this->totalBytes;
    }

    public function setTotalBytes(float $totalBytes): static
    {
        $this->totalBytes = $totalBytes;

        return $this;
    }
}
