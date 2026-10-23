<?php

namespace App\Domain;

use InvalidArgumentException;

final readonly class User
{
    public string $username;

    public function __construct(
        public string $id,
        string $username,
        private string $passwordHash,
        public string $role,
    ) {
        $username = mb_strtolower(trim($username), 'UTF-8');

        if ($username === '' || $passwordHash === '') {
            throw new InvalidArgumentException(
                'El usuario y su hash son obligatorios.'
            );
        }

        if (!in_array($role, ['admin', 'librarian'], true)) {
            throw new InvalidArgumentException('El rol no es válido.');
        }

        $this->username = $username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}