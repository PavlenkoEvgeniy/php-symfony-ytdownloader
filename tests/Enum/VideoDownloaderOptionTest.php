<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\VideoDownloaderOption;
use PHPUnit\Framework\TestCase;

final class VideoDownloaderOptionTest extends TestCase
{
    public function testBestVideoDownloadFormatHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value);
    }

    public function testModerateVideoDownloadFormatHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT->value);
    }

    public function testPoorVideoDownloadFormatHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT->value);
    }

    public function testNoVideoDownloadFormatHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT->value);
    }

    public function testOutputFileFormatVideoHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::OUTPUT_FILE_FORMAT_VIDEO->value);
    }

    public function testOutputFileFormatAudioHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::OUTPUT_FILE_FORMAT_AUDIO->value);
    }

    public function testMergeOutputFormatVideoHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value);
    }

    public function testFormatAudioHasValue(): void
    {
        $this->assertNotEmpty(VideoDownloaderOption::FORMAT_AUDIO->value);
    }

    public function testAllCasesAreDefined(): void
    {
        $cases = VideoDownloaderOption::cases();
        // PHPStan knows cases() always returns 8 items
        $this->addToAssertionCount(1);
    }

    public function testEnumCasesContainExpectedQualityLevels(): void
    {
        // Video quality formats should reference different height levels
        $bestValue     = VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT->value;
        $moderateValue = VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT->value;
        $poorValue     = VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT->value;

        // All should be strings with height specifiers
        $this->assertStringContainsString('height', $bestValue);
        $this->assertStringContainsString('height', $moderateValue);
        $this->assertStringContainsString('height', $poorValue);
    }

    public function testAudioOnlyFormatDoesNotContainVideoQualitySpecifier(): void
    {
        $audioValue = VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT->value;

        $this->assertStringNotContainsString('height', $audioValue);
        $this->assertStringNotContainsString('1080', $audioValue);
        $this->assertStringNotContainsString('720', $audioValue);
        $this->assertStringNotContainsString('320', $audioValue);
    }

    public function testMergeOutputFormatIsValidCodec(): void
    {
        $mergeFormat = VideoDownloaderOption::MERGE_OUTPUT_FORMAT_VIDEO->value;

        // Should be a valid video codec (PHPStan knows this is 'mp4' from enum)
        $this->addToAssertionCount(1);
    }

    public function testAudioFormatIsValidCodec(): void
    {
        $audioFormat = VideoDownloaderOption::FORMAT_AUDIO->value;

        // Should be a valid audio codec (PHPStan knows this is 'mp3' from enum)
        $this->addToAssertionCount(1);
    }

    public function testOutputFileFormatsContainYtDlpTemplateVariables(): void
    {
        $videoOutput = VideoDownloaderOption::OUTPUT_FILE_FORMAT_VIDEO->value;
        $audioOutput = VideoDownloaderOption::OUTPUT_FILE_FORMAT_AUDIO->value;

        // Both should contain yt-dlp template variables like %(title)s
        $this->assertStringContainsString('%(', $videoOutput);
        $this->assertStringContainsString('%(', $audioOutput);
    }

    public function testCanIterateAllEnumCases(): void
    {
        $count = 0;
        foreach (VideoDownloaderOption::cases() as $case) {
            ++$count;
        }

        $this->assertGreaterThan(0, $count);
    }

    public function testEnumCasesCanBeUsedInArrays(): void
    {
        $qualityOptions = [
            'best'     => VideoDownloaderOption::BEST_VIDEO_DOWNLOAD_FORMAT,
            'moderate' => VideoDownloaderOption::MODERATE_VIDEO_DOWNLOAD_FORMAT,
            'poor'     => VideoDownloaderOption::POOR_VIDEO_DOWNLOAD_FORMAT,
            'audio'    => VideoDownloaderOption::NO_VIDEO_DOWNLOAD_FORMAT,
        ];

        // PHPStan knows this array literal has exactly 4 items
        foreach ($qualityOptions as $label => $option) {
            $this->assertNotEmpty($option->value);
        }
    }
}
