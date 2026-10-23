<?php

namespace App\Adapters;

use App\Application\Ports\PasswordHasher;

final class ArgonPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}