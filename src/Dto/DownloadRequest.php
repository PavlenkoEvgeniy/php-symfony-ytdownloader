<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\DownloadQuality;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DownloadRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url]
        public string $url = '',
        #[Assert\Choice(choices: DownloadQuality::VALUES)]
        public string $quality = 'moderate',
    ) {
    }
}
