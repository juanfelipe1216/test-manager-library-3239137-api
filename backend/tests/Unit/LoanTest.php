<?php

namespace Tests\Unit;

use App\Domain\Book;
use App\Domain\Isbn;
use App\Domain\Loan;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private function book(string $id = 'book-1'): Book
    {
        return new Book(
            $id,
            'Título original',
            new Isbn('9780307474728'),
            1,
            1,
        );
    }

    private function loan(): Loan
    {
        return new Loan(
            'loan-1',
            'member-1',
            'user-1',
            new DateTimeImmutable('2026-10-01T12:00:00Z'),
        );
    }

    public function test_lending_sets_due_date_and_reduces_stock(): void
    {
        $book = $this->book();
        $loan = $this->loan();
        $loan->addItem('item-1', $book, 'Novela');

        $this->assertSame(0, $book->availableCopies());
        $this->assertSame(
            '2026-10-15T12:00:00+00:00',
            $loan->items()[0]->dueAt->format(DATE_ATOM),
        );
    }

    public function test_status_is_open_before_due_date(): void
    {
        $loan = $this->loan();
        $loan->addItem('item-1', $this->book(), 'Novela');

        $this->assertSame('open', $loan->statusAt(
            new DateTimeImmutable('2026-10-02T12:00:00Z'),
        ));
    }

    public function test_status_is_overdue_after_due_date(): void
    {
        $loan = $this->loan();
        $loan->addItem('item-1', $this->book(), 'Novela');

        $this->assertSame('overdue', $loan->statusAt(
            new DateTimeImmutable('2026-10-16T12:00:00Z'),
        ));
    }

    public function test_returning_last_item_closes_loan(): void
    {
        $book = $this->book();
        $loan = $this->loan();
        $loan->addItem('item-1', $book, 'Novela');
        $now = new DateTimeImmutable('2026-10-02T12:00:00Z');

        $loan->returnItem('item-1', $book, $now);

        $this->assertSame(1, $book->availableCopies());
        $this->assertSame($now, $loan->items()[0]->returnedAt);
        $this->assertSame('closed', $loan->statusAt($now));
    }

    public function test_returning_twice_does_not_increase_stock(): void
    {
        $book = $this->book();
        $loan = $this->loan();
        $loan->addItem('item-1', $book, 'Novela');
        $now = new DateTimeImmutable('2026-10-02T12:00:00Z');
        $loan->returnItem('item-1', $book, $now);

        try {
            $loan->returnItem('item-1', $book, $now);
            $this->fail('Debía rechazar la segunda devolución.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'La línea ya fue devuelta.',
                $exception->getMessage(),
            );
            $this->assertSame(1, $book->availableCopies());
        }
    }

    public function test_duplicate_book_is_rejected_before_stock_changes(): void
    {
        $book = new Book(
            'book-1', 'Libro', new Isbn('9780307474728'), 2, 2,
        );
        $loan = $this->loan();
        $loan->addItem('item-1', $book, 'Novela');

        try {
            $loan->addItem('item-2', $book, 'Novela');
            $this->fail('Debía rechazar el libro repetido.');
        } catch (DomainException) {
            $this->assertSame(1, $book->availableCopies());
            $this->assertCount(1, $loan->items());
        }
    }

    public function test_fourth_book_is_rejected(): void
    {
        $loan = $this->loan();

        for ($i = 1; $i <= 3; $i++) {
            $loan->addItem("item-$i", $this->book("book-$i"), 'Novela');
        }

        $fourthBook = $this->book('book-4');

        try {
            $loan->addItem('item-4', $fourthBook, 'Novela');
            $this->fail('Debía rechazar el cuarto libro.');
        } catch (DomainException) {
            $this->assertCount(3, $loan->items());
            $this->assertSame(1, $fourthBook->availableCopies());
        }
    }

    public function test_renaming_book_preserves_snapshot(): void
    {
        $book = $this->book();
        $loan = $this->loan();
        $loan->addItem('item-1', $book, 'Novela');

        $book->rename('Título nuevo');

        $this->assertSame(
            'Título original',
            $loan->items()[0]->bookTitle,
        );
        $this->assertSame('Título nuevo', $book->title());
    }
}