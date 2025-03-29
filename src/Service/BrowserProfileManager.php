<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class BrowserProfileManager
{
    private string $profilesBaseDir;

    public function __construct(string $projectDir)
    {
        $this->profilesBaseDir = $projectDir . '/var/chrome_profiles';
        $this->ensureBaseDirExists();
    }

    public function createProfile(): string
    {
        $profileDir = $this->profilesBaseDir . '/profile_' . time();

        (new Filesystem())->mkdir($profileDir, 0777);

        // Важно: устанавливаем правильные права
        $this->fixPermissions($profileDir);

        return $profileDir;
    }

    public function removeProfile(string $profileDir): void
    {
        (new Filesystem())->remove($profileDir);
    }

    private function ensureBaseDirExists(): void
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->profilesBaseDir)) {
            $fs->mkdir($this->profilesBaseDir, 0777);
            $this->fixPermissions($this->profilesBaseDir);
        }
    }

    private function fixPermissions(string $path): void
    {
        // Если работает под root (например, в Docker)
        if (0 === \posix_geteuid()) {
            $process = new Process(['chown', '-R', 'www-data:www-data', $path]);
            $process->run();
        }

        // Всегда устанавливаем полные права на папку профиля
        (new Filesystem())->chmod($path, 0777, 0000, true);
    }
}
