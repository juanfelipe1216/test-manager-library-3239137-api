<?php

namespace Tests\Unit;

use App\Domain\Book;
use App\Domain\Isbn;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    private function book(int $total = 2, int $available = 2): Book
    {
        return new Book(
            '11111111-1111-4111-8111-111111111111',
            'Libro de prueba',
            new Isbn('9780307474728'),
            $total,
            $available,
        );
    }

    public function test_lending_reduces_available_copies(): void
    {
        $book = $this->book();
        $book->withdraw();

        $this->assertSame(1, $book->availableCopies());
    }

    public function test_cannot_lend_without_available_copies(): void
    {
        $book = $this->book(1, 0);

        $this->expectException(DomainException::class);

        $book->withdraw();
    }

    public function test_returning_increases_available_copies(): void
    {
        $book = $this->book(2, 1);
        $book->restock();

        $this->assertSame(2, $book->availableCopies());
    }

    public function test_returning_cannot_exceed_total(): void
    {
        $book = $this->book();

        $this->expectException(DomainException::class);

        $book->restock();
    }

    public function test_changing_total_preserves_borrowed_count(): void
    {
        $book = $this->book(3, 1);
        $book->changeTotal(5);

        $this->assertSame(5, $book->totalCopies());
        $this->assertSame(3, $book->availableCopies());
    }

    public function test_cannot_reduce_total_below_borrowed_count(): void
    {
        $book = $this->book(3, 1);

        $this->expectException(DomainException::class);

        $book->changeTotal(1);
    }

    public function test_soft_delete_preserves_book_identity(): void
    {
        $book = $this->book();
        $now = new DateTimeImmutable('2026-10-22T23:00:00Z');

        $book->softDelete($now);

        $this->assertSame($now, $book->deletedAt());
        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $book->id,
        );
    }

    public function test_cannot_delete_book_with_open_loans(): void
    {
        $book = $this->book(2, 1);

        $this->expectException(DomainException::class);

        $book->softDelete(new DateTimeImmutable());
    }

    public function test_cannot_lend_deleted_book(): void
    {
        $book = $this->book();
        $book->softDelete(new DateTimeImmutable());

        $this->expectException(DomainException::class);

        $book->withdraw();
    }
}