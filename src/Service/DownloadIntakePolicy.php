<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Exception\DownloadIntakeDisabledException;
use App\Repository\DownloadSettingsRepositoryInterface;

/**
 * The web download intake may be switched off by an admin: new tasks are then
 * rejected from the UI and both APIs, while the Telegram bot keeps working.
 * Already accepted tasks are not revoked.
 */
final readonly class DownloadIntakePolicy implements DownloadIntakePolicyInterface
{
    public function __construct(
        private DownloadSettingsRepositoryInterface $settingsRepository,
    ) {
    }

    #[\Override]
    public function isWebIntakeEnabled(): bool
    {
        return $this->settingsRepository->getSettings()->getWebIntakeEnabled();
    }

    #[\Override]
    public function assertAccepts(string $origin): void
    {
        if (DownloadTask::ORIGIN_TELEGRAM === $origin) {
            return;
        }

        if (!$this->isWebIntakeEnabled()) {
            throw DownloadIntakeDisabledException::create();
        }
    }
}
