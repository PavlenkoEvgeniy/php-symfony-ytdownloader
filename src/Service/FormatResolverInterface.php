<?php

declare(strict_types=1);

namespace App\Service;

interface FormatResolverInterface
{
    /**
     * @return array{string, bool}
     */
    public function resolve(string $format): array;
}
