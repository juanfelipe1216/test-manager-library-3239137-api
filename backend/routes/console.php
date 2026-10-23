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
Artisan::command('library:credentials {username}', function (
    \App\Application\Ports\PasswordHasher $passwords,
) {
    $username=mb_strtolower(trim($this->argument('username')),'UTF-8');
    if(!preg_match('/^[a-z0-9._-]{3,120}$/D',$username)) { $this->error('Usuario no válido.'); return 1; }
    $password=$this->secret('Nueva contraseña (mínimo 8 caracteres; no se muestra)');
    $confirm=$this->secret('Repite la contraseña');
    if(!is_string($password) || strlen($password)<8 || $password!==$confirm) { $this->error('Las contraseñas no coinciden o son muy cortas.'); return 1; }
    $old=\Illuminate\Support\Facades\DB::table('library.user')
        ->where('username',mb_strtolower(trim((string)config('library.admin_username')),'UTF-8'))->where('role','admin')->first();
    if(!$old) { $this->error('No se encontró el administrador configurado.'); return 1; }
    $taken=\Illuminate\Support\Facades\DB::table('library.user')->where('username',$username)->where('id','!=',$old->id)->exists();
    if($taken) { $this->error('El nombre de usuario ya existe.'); return 1; }
    $path=base_path('.env'); $contents=file_get_contents($path);
    foreach(['ADMIN_USERNAME'=>$username,'ADMIN_PASSWORD'=>$password] as $key=>$value) {
        $literal=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $literal=str_replace('$','\$',$literal);
        $line=$key.'='.$literal;
        if(preg_match('/^'.preg_quote($key,'/').'=.*/m',$contents))
            $contents=preg_replace_callback('/^'.preg_quote($key,'/').'=.*/m',fn()=>$line,$contents);
        else $contents.="\n".$line."\n";
    }
    if(file_put_contents($path,$contents)===false) { $this->error('No se pudo actualizar el .env local.'); return 1; }
    \Illuminate\Support\Facades\DB::table('library.user')->where('id',$old->id)
        ->update(['username'=>$username,'password_hash'=>$passwords->hash($password)]);
    $this->info('Acceso actualizado. Usuario: '.$username.'. La contraseña no se ha mostrado ni registrado.');
    return 0;
})->purpose('Actualizar el administrador local conservando su identidad e historial');
