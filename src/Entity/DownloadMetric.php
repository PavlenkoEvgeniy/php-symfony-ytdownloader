<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\PropertyIsNotSetException;
use App\Repository\DownloadMetricRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DownloadMetricRepository::class)]
#[ORM\Table(name: 'download_metric')]
class DownloadMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'total_bytes', type: Types::FLOAT)]
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

    public function getIdOrFail(): int
    {
        return $this->id ?? throw PropertyIsNotSetException::forProperty('id');
    }

    public function getTotalBytesOrFail(): float
    {
        return $this->totalBytes ?? throw PropertyIsNotSetException::forProperty('totalBytes');
    }
}
