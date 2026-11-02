<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Source;
use App\Exception\PropertyIsNotSetException;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    /**
     * Verifies every source setter returns the same instance.
     */
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

    /**
     * Verifies source getters return the values assigned to the entity.
     */
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

    /**
     * Verifies a new source has no identifier before persistence.
     */
    public function testSourceIdIsNullByDefault(): void
    {
        $source = new Source();

        $this->assertNull($source->getId());
    }

    /**
     * Verifies a new source has no description.
     */
    public function testSourceDescriptionIsNullableByDefault(): void
    {
        $source = new Source();

        $this->assertNull($source->getDescription());
    }

    /**
     * Verifies filename, filepath, description and size are null before being set.
     */
    public function testSourcePropertiesAreNullBeforeSet(): void
    {
        $source = new Source();

        $this->assertNull($source->getFilename());
        $this->assertNull($source->getFilepath());
        $this->assertNull($source->getDescription());
        $this->assertNull($source->getSize());
    }

    /**
     * Verifies a set description can be cleared to null.
     */
    public function testSourceCanSetDescriptionToNull(): void
    {
        $source = new Source();
        $source->setDescription('Some description');
        $source->setDescription(null);

        $this->assertNull($source->getDescription());
    }

    /**
     * Verifies the filename is stored and returned unchanged.
     */
    public function testSourceFilenameCanBeSet(): void
    {
        $source   = new Source();
        $filename = 'my-video-file.mp4';

        $source->setFilename($filename);

        $this->assertSame($filename, $source->getFilename());
    }

    /**
     * Verifies the filepath is stored and returned unchanged.
     */
    public function testSourceFilepathCanBeSet(): void
    {
        $source   = new Source();
        $filepath = '/absolute/path/to/video.mp4';

        $source->setFilepath($filepath);

        $this->assertSame($filepath, $source->getFilepath());
    }

    /**
     * Verifies a zero size is stored unchanged.
     */
    public function testSourceSizeCanBeZero(): void
    {
        $source = new Source();
        $source->setSize(0.0);

        $this->assertSame(0.0, $source->getSize());
    }

    /**
     * Verifies a negative size is stored unchanged.
     */
    public function testSourceSizeCanBeNegative(): void
    {
        $source = new Source();
        $source->setSize(-1.5);

        $this->assertSame(-1.5, $source->getSize());
    }

    /**
     * Verifies a large size is stored without precision loss.
     */
    public function testSourceSizeCanBeLarge(): void
    {
        $source    = new Source();
        $largeSize = 1024.0 * 1024.0 * 1024.0 * 100; // 100GB

        $source->setSize($largeSize);

        $this->assertSame($largeSize, $source->getSize());
    }

    /**
     * Verifies the "or fail" getters return assigned values.
     */
    public function testOrFailGettersReturnAssignedValues(): void
    {
        $now = new \DateTime();

        $source = new Source();

        $source->setFilename('video.mp4')
            ->setFilepath('/tmp/video.mp4')
            ->setSize(12.5)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $this->assertSame('video.mp4', $source->getFilenameOrFail());
        $this->assertSame('/tmp/video.mp4', $source->getFilepathOrFail());
        $this->assertSame(12.5, $source->getSizeOrFail());
        $this->assertSame($now, $source->getCreatedAtOrFail());
        $this->assertSame($now, $source->getUpdatedAtOrFail());
    }

    /**
     * Verifies the identifier "or fail" getter throws before persistence.
     */
    public function testIdOrFailThrowsForUnsavedSource(): void
    {
        $this->expectException(PropertyIsNotSetException::class);

        new Source()->getIdOrFail();
    }
}
