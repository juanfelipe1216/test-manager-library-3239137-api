<?php

namespace App\Application\Services;

use App\Application\BusinessError;
use App\Application\Ports\MemberRegistry;
use App\Application\Ports\UnitOfWork;

final class DeleteMemberService
{
    public function __construct(private MemberRegistry $members, private UnitOfWork $work) {}

    public function execute(string $id): void
    {
        $this->work->run(function () use ($id) {
            // Serialize against lending with the same member lock.
            $this->members->lock($id);
            $member = $this->members->get($id);
            if ($member['loans_count'] > 0) {
                throw new BusinessError('MEMBER_HAS_LOANS', 'No se puede eliminar este socio porque tiene préstamos registrados. Su historial debe conservarse.', 409);
            }
            $this->members->delete($id);
        });
    }
}
