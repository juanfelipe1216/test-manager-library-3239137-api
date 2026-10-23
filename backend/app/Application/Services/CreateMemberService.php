<?php

namespace App\Application\Services;

use App\Application\Ports\MemberRegistry;
use App\Domain\Member;

final class CreateMemberService
{
    public function __construct(private MemberRegistry $members) {}

    public function execute(string $id, array $data): array
    {
        $member = new Member($id, $data['full_name'], $data['document_number']);
        $this->members->create($member);

        return $this->members->get($id);
    }
}
