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

    public function __construct(
        bool $webIntakeEnabled = true,
    ) {
        $this->webIntakeEnabled = $webIntakeEnabled;
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
}
