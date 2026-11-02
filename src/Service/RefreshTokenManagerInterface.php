<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

interface RefreshTokenManagerInterface
{
    /**
     * @return array{token: string, refresh_token: string, refresh_token_expires_at: string}
     */
    public function issueTokens(User $user): array;

    /**
     * @return array{token: string, refresh_token: string, refresh_token_expires_at: string}|null
     */
    public function refresh(string $tokenValue): ?array;
}
