<?php

declare(strict_types=1);

namespace App\Service;

use DateInterval;
use DateTimeImmutable;

final class TokenService
{
    public function __construct(private int $ttlMinutes)
    {
    }

    public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function expiresAt(?string $override = null): ?string
    {
        if ($override !== null) {
            return $override;
        }
        $now = new DateTimeImmutable();
        return $now->add(new DateInterval('PT' . $this->ttlMinutes . 'M'))->format('Y-m-d H:i:s');
    }

    public function isExpired(array $task): bool
    {
        if (empty($task['expires_at'])) {
            return false;
        }
        $expires = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $task['expires_at']);
        if ($expires === false) {
            return false;
        }
        return $expires < new DateTimeImmutable('now');
    }
}

