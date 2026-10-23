<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Artisan::command('library:admin', function (
    \App\Application\Ports\UserRepository $users,
    \App\Application\Ports\PasswordHasher $passwords,
) {
    $username = mb_strtolower(
        trim((string) config('library.admin_username')),
        'UTF-8',
    );
    $password = (string) config('library.admin_password');

    if ($username === '' || $password === '') {
        $this->error('Configura ADMIN_USERNAME y ADMIN_PASSWORD.');
        return 1;
    }

    if ($users->findByUsername($username) !== null) {
        $this->info('El usuario ya existe; no se modificó.');
        return 0;
    }

    $users->save(new \App\Domain\User(
        (string) \Illuminate\Support\Str::uuid(),
        $username,
        $passwords->hash($password),
        'admin',
    ));

    $this->info('Administrador inicial creado.');
    return 0;
});