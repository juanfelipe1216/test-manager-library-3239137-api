<?php

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {
        if ($to < $from) {
            throw new InvalidArgumentException(
                'La fecha final no puede ser anterior a la inicial.'
            );
        }
    }
}