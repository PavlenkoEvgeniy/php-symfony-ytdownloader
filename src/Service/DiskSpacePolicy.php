<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InsufficientDiskSpaceException;
use App\Repository\DownloadSettingsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rejects new downloads while the download volume is below the configured
 * free-space minimum (see CONTEXT.md "Disk Pressure").
 * Unset minimum follows MIN_FREE_DISK_MB; 0 disables the guard.
 */
final readonly class DiskSpacePolicy implements DiskSpacePolicyInterface
{
    public function __construct(
        #[Autowire(env: 'string:resolve:APP_DOWNLOADS_DIR')]
        private string $downloadsDir,
        #[Autowire(env: 'int:MIN_FREE_DISK_MB')]
        private int $defaultMinFreeMb,
        private DownloadSettingsRepositoryInterface $settingsRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function getFreeBytes(): float
    {
        return \disk_free_space($this->downloadsDir) ?: 0.0;
    }

    #[\Override]
    public function getMinFreeBytes(): int
    {
        return $this->getMinFreeMegabytes() * 1024 * 1024;
    }

    #[\Override]
    public function isLow(): bool
    {
        return $this->getFreeBytes() < $this->getMinFreeBytes();
    }

    #[\Override]
    public function assertCanAccept(): void
    {
        if (!$this->isLow()) {
            return;
        }

        $this->logger->warning('Rejected a download request: free disk space is below the threshold', [
            'free' => $this->getFreeBytes(),
            'min'  => $this->getMinFreeBytes(),
        ]);

        throw InsufficientDiskSpaceException::create($this->getFreeBytes(), $this->getMinFreeBytes());
    }

    private function getMinFreeMegabytes(): int
    {
        $minFreeMb = $this->settingsRepository->getSettings()->getMinFreeDiskMb() ?? $this->defaultMinFreeMb;

        return \max(0, $minFreeMb);
    }
}
