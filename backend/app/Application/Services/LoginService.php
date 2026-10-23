<?php

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use DomainException;

final class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private TokenIssuer $tokens,
    ) {}

    public function execute(string $username, string $password): array
    {
        $username = mb_strtolower(trim($username), 'UTF-8');
        $user = $this->users->findByUsername($username);

        if (
            $user === null ||
            !$this->passwords->verify($password, $user->passwordHash())
        ) {
            throw new DomainException('Credenciales inválidas.');
        }

        return [
            'access_token' => $this->tokens->issue(
                $user->id,
                $user->role,
                28800,
            ),
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'role' => $user->role,
        ];
    }
}