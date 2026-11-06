<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DownloadSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singleton row with the settings that are changed at runtime by an admin.
 *
 * A missing row means the defaults: the web download intake is enabled.
 */
#[ORM\Entity(repositoryClass: DownloadSettingsRepository::class)]
#[ORM\Table(name: 'download_settings')]
class DownloadSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    public private(set) int $id;

    #[ORM\Column(name: 'web_intake_enabled', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $webIntakeEnabled;

    #[ORM\Column(name: 'progress_interval_minutes', type: Types::INTEGER, options: ['default' => 5])]
    private int $progressIntervalMinutes;

    public function __construct(
        bool $webIntakeEnabled = true,
        int $progressIntervalMinutes = 5,
    ) {
        $this->webIntakeEnabled        = $webIntakeEnabled;
        $this->progressIntervalMinutes = $progressIntervalMinutes;
    }

    public function getWebIntakeEnabled(): bool
    {
        return $this->webIntakeEnabled;
    }

    public function setWebIntakeEnabled(bool $webIntakeEnabled): static
    {
        $this->webIntakeEnabled = $webIntakeEnabled;

        return $this;
    }

    public function getProgressIntervalMinutes(): int
    {
        return $this->progressIntervalMinutes;
    }

    public function setProgressIntervalMinutes(?int $progressIntervalMinutes): static
    {
        $this->progressIntervalMinutes = $progressIntervalMinutes ?? 5;

        return $this;
    }
}
