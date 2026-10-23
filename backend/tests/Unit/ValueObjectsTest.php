<?php

namespace Tests\Unit;

use App\Domain\DateRange;
use App\Domain\Isbn;
use App\Domain\Quantity;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_accepts_thirteen_digit_isbn(): void
    {
        $isbn = new Isbn('9780307474728');

        $this->assertSame('9780307474728', $isbn->value);
    }

    public function test_rejects_short_isbn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Isbn('123');
    }

    public function test_rejects_non_numeric_isbn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Isbn('978030747472X');
    }

    public function test_accepts_positive_quantity(): void
    {
        $this->assertSame(3, (new Quantity(3))->value);
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quantity(0);
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quantity(-1);
    }

    public function test_accepts_ordered_dates(): void
    {
        $from = new DateTimeImmutable('2026-10-01T00:00:00Z');
        $to = new DateTimeImmutable('2026-10-31T00:00:00Z');

        $range = new DateRange($from, $to);

        $this->assertSame($from, $range->from);
        $this->assertSame($to, $range->to);
    }

    public function test_accepts_equal_dates(): void
    {
        $date = new DateTimeImmutable('2026-10-01T00:00:00Z');

        $range = new DateRange($date, $date);

        $this->assertSame($range->from, $range->to);
    }

    public function test_rejects_reversed_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DateRange(
            new DateTimeImmutable('2026-10-31T00:00:00Z'),
            new DateTimeImmutable('2026-10-01T00:00:00Z'),
        );
    }
}