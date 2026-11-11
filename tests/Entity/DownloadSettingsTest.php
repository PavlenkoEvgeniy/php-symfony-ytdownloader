<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DownloadSettings;
use PHPUnit\Framework\TestCase;

final class DownloadSettingsTest extends TestCase
{
    /**
     * Verifies the minimum free disk space defaults to unset.
     */
    public function testMinFreeDiskMbDefaultsToNull(): void
    {
        $settings = new DownloadSettings();

        $this->assertNull($settings->getMinFreeDiskMb());
    }

    /**
     * Verifies null round-trips through the setter: unset stays unset.
     */
    public function testMinFreeDiskMbNullRoundTrips(): void
    {
        $settings = new DownloadSettings(minFreeDiskMb: 1024);
        $settings->setMinFreeDiskMb(null);

        $this->assertNull($settings->getMinFreeDiskMb());
    }

    /**
     * Verifies a saved threshold is returned as-is.
     */
    public function testMinFreeDiskMbReturnsSavedValue(): void
    {
        $settings = new DownloadSettings(minFreeDiskMb: 5120);
        $settings->setMinFreeDiskMb(0);

        $this->assertSame(0, $settings->getMinFreeDiskMb());

        $settings->setMinFreeDiskMb(7);

        $this->assertSame(7, $settings->getMinFreeDiskMb());
    }
}
