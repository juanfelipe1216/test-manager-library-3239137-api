<?php
namespace App\Application\Services;
use App\Application\Ports\{UserRepository,PasswordHasher};
use App\Application\BusinessError;
use App\Domain\User;
final class CreateLibrarianService {
    public function __construct(private UserRepository $users,private PasswordHasher $passwords) {}
    public function execute(string $id,array $data): array {
        if($data['role']!=='librarian') throw new BusinessError('VALIDATION_ERROR','Solo se permite crear bibliotecarios.');
        $username=mb_strtolower(trim($data['username']),'UTF-8');
        if($this->users->findByUsername($username)) throw new BusinessError('USERNAME_TAKEN','El usuario ya existe.',409);
        $user=new User($id,$username,$this->passwords->hash($data['password']),'librarian');
        $this->users->save($user); return ['id'=>$id,'username'=>$user->username,'role'=>$user->role];
    }
}