<?php

namespace App\Application\Ports;

interface TokenVerifier
{
    public function verify(string $token): array;
}
