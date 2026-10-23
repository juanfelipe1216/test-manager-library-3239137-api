<?php
namespace App\Application\Services;
use App\Application\Ports\{LoanLedger,UnitOfWork,Clock};
final class ReturnItemService {
    public function __construct(private LoanLedger $loans,private UnitOfWork $work,private Clock $clock) {}
    public function execute(string $loanId,string $itemId): array {
        return $this->work->run(fn()=>$this->loans->returnLine($loanId,$itemId,$this->clock->now()));
    }
}