<?php

namespace App\Domain;

use DateTimeImmutable;
use DomainException;

final readonly class LoanItem
{
    public function __construct(
        public string $id,
        public string $bookId,
        public string $bookTitle,
        public string $isbn,
        public string $categoryName,
        public DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $returnedAt = null,
    ) {}

    public function returned(DateTimeImmutable $now): self
    {
        if ($this->returnedAt !== null) {
            throw new DomainException('La línea ya fue devuelta.');
        }

        return new self(
            $this->id,
            $this->bookId,
            $this->bookTitle,
            $this->isbn,
            $this->categoryName,
            $this->dueAt,
            $now,
        );
    }
}