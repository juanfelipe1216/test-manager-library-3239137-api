<?php

namespace App\Application\Ports;

use App\Domain\Loan;
use DateTimeImmutable;

interface LoanLedger
{
    public function outstanding(string $memberId): array;

    public function append(Loan $loan): void;

    public function get(string $id, DateTimeImmutable $now): array;

    public function search(array $filters, DateTimeImmutable $now): array;

    public function overdue(DateTimeImmutable $now): array;

    public function returnLine(string $loanId, string $itemId, DateTimeImmutable $now): array;
}
