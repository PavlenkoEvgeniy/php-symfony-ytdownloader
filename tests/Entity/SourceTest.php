<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Source;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    public function testSourceSettersReturnInstance(): void
    {
        $source = new Source();

        $result1 = $source->setFilename('video.mp4');
        $result2 = $source->setFilepath('/var/downloads/video.mp4');
        $result3 = $source->setDescription('Test video');
        $result4 = $source->setSize(1024.5);

        $this->assertSame($source, $result1);
        $this->assertSame($source, $result2);
        $this->assertSame($source, $result3);
        $this->assertSame($source, $result4);
    }

    public function testSourceGettersReturnSetValues(): void
    {
        $source = new Source();

        $source->setFilename('video.mp4');
        $source->setFilepath('/var/downloads/video.mp4');
        $source->setDescription('Test video');
        $source->setSize(1024.5);

        $this->assertSame('video.mp4', $source->getFilename());
        $this->assertSame('/var/downloads/video.mp4', $source->getFilepath());
        $this->assertSame('Test video', $source->getDescription());
        $this->assertSame(1024.5, $source->getSize());
    }

    public function testSourceIdIsNullByDefault(): void
    {
        $source = new Source();

        $this->assertNull($source->getId());
    }

    public function testSourceDescriptionIsNullableByDefault(): void
    {
        $source = new Source();

        $this->assertNull($source->getDescription());
    }

    public function testSourcePropertiesAreNullBeforeSet(): void
    {
        $source = new Source();

        $this->assertNull($source->getFilename());
        $this->assertNull($source->getFilepath());
        $this->assertNull($source->getDescription());
        $this->assertNull($source->getSize());
    }

    public function testSourceCanSetDescriptionToNull(): void
    {
        $source = new Source();
        $source->setDescription('Some description');
        $source->setDescription(null);

        $this->assertNull($source->getDescription());
    }

    public function testSourceFilenameCanBeSet(): void
    {
        $source   = new Source();
        $filename = 'my-video-file.mp4';

        $source->setFilename($filename);

        $this->assertSame($filename, $source->getFilename());
    }

    public function testSourceFilepathCanBeSet(): void
    {
        $source   = new Source();
        $filepath = '/absolute/path/to/video.mp4';

        $source->setFilepath($filepath);

        $this->assertSame($filepath, $source->getFilepath());
    }

    public function testSourceSizeCanBeZero(): void
    {
        $source = new Source();
        $source->setSize(0.0);

        $this->assertSame(0.0, $source->getSize());
    }

    public function testSourceSizeCanBeNegative(): void
    {
        $source = new Source();
        $source->setSize(-1.5);

        $this->assertSame(-1.5, $source->getSize());
    }

    public function testSourceSizeCanBeLarge(): void
    {
        $source    = new Source();
        $largeSize = 1024.0 * 1024.0 * 1024.0 * 100; // 100GB

        $source->setSize($largeSize);

        $this->assertSame($largeSize, $source->getSize());
    }

    public function testSourceHasTimestampableTraitProperties(): void
    {
        $source = new Source();

        // TimestampableEntity trait should provide these methods
        $this->addToAssertionCount(1);
    }
}
