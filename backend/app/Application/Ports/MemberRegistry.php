<?php
namespace App\Application\Ports;
use App\Domain\Member;
interface MemberRegistry {
    public function get(string $id): array;
    public function search(array $filters): array;
    public function create(Member $member): void;
    public function lock(string $id): void;
}