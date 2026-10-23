<?php

namespace App\Domain;

use DateTimeImmutable;
use DomainException;

final class Loan
{
    private array $items = [];

    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
    ) {}

    public function items(): array
    {
        return array_values($this->items);
    }

    public function addItem(
        string $itemId,
        Book $book,
        string $categoryName,
    ): void {
        if (count($this->items) >= 3) {
            throw new DomainException(
                'Un préstamo admite como máximo tres libros.'
            );
        }

        if (isset($this->items[$itemId])) {
            throw new DomainException('El identificador de línea ya existe.');
        }

        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DomainException(
                    'No se puede repetir un libro en el préstamo.'
                );
            }
        }

        $book->withdraw();

        $this->items[$itemId] = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn->value,
            $categoryName,
            $this->loanedAt->modify('+14 days'),
        );
    }

    public function returnItem(
        string $itemId,
        Book $book,
        DateTimeImmutable $now,
    ): void {
        $item = $this->items[$itemId] ?? null;

        if ($item === null || $item->bookId !== $book->id) {
            throw new DomainException('La línea no corresponde al libro.');
        }

        if ($now < $this->loanedAt) {
            throw new DomainException(
                'La devolución no puede ser anterior al préstamo.'
            );
        }

        // Valida la devolución antes de modificar las existencias.
        $returnedItem = $item->returned($now);

        $book->restock();
        $this->items[$itemId] = $returnedItem;
    }

    public function statusAt(DateTimeImmutable $now): string
    {
        if ($this->items === []) {
            throw new DomainException(
                'El préstamo debe contener al menos un libro.'
            );
        }

        $hasOpenItems = false;

        foreach ($this->items as $item) {
            if ($item->returnedAt !== null) {
                continue;
            }

            $hasOpenItems = true;

            if ($item->dueAt < $now) {
                return 'overdue';
            }
        }

        return $hasOpenItems ? 'open' : 'closed';
    }
}