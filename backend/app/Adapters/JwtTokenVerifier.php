<?php
namespace App\Adapters;
use App\Application\Ports\TokenVerifier;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
final class JwtTokenVerifier implements TokenVerifier {
    public function __construct(private string $secret) {}
    public function verify(string $token): array {
        $claims=(array)JWT::decode($token,new Key($this->secret,'HS256'));
        if (!isset($claims['sub'],$claims['role'],$claims['exp']) || !in_array($claims['role'],['admin','librarian'],true)) throw new \UnexpectedValueException('Token inválido.');
        return $claims;
    }
}