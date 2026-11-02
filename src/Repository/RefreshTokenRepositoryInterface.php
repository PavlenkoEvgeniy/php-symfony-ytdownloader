<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function findOneByToken(string $token): ?RefreshToken;
}
