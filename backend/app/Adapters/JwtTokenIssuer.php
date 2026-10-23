<?php

namespace App\Adapters;

use App\Application\Ports\Clock;
use App\Application\Ports\TokenIssuer;
use Firebase\JWT\JWT;
use RuntimeException;

final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private string $secret,
        private Clock $clock,
    ) {
        if (strlen($secret) < 32) {
            throw new RuntimeException('Falta configurar JWT_SECRET.');
        }
    }

    public function issue(string $userId, string $role, int $seconds): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $seconds,
        ], $this->secret, 'HS256');
    }
}