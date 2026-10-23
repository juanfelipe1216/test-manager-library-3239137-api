<?php

namespace App\Application\Ports;

interface CoverStorage
{
    public function put(string $bytes, string $mime): string;

    public function read(string $key): ?array;

    public function delete(string $key): void;
}
