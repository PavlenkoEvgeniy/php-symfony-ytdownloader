<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DownloadTask;
use App\Entity\Source;

interface SourceManagerInterface
{
    public function findByFilename(string $filename): ?Source;

    public function createFromDownloadedFile(
        string $filename,
        string $path,
        float $size,
        ?DownloadTask $downloadTask = null,
        ?string $origin = null,
    ): Source;

    public function flush(): void;
}
