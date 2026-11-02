<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ApiProblem
{
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public string $instance,
    ) {
    }
}
