<?php

namespace App\Adapters;

use App\Application\Ports\UserRepository;
use App\Domain\User;
use Illuminate\Support\Facades\DB;

final class PostgresUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $row = DB::table('library.user')
            ->where('username', $username)
            ->first();

        if ($row === null) {
            return null;
        }

        return new User(
            $row->id,
            $row->username,
            $row->password_hash,
            $row->role,
        );
    }

    public function save(User $user): void
    {
        DB::table('library.user')->insert([
            'id' => $user->id,
            'username' => $user->username,
            'password_hash' => $user->passwordHash(),
            'role' => $user->role,
        ]);
    }
}
