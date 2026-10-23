<?php

namespace App\Domain;

use DateTimeImmutable;
use DomainException;

final class Book
{
    public function __construct(
        public readonly string $id,
        private string $title,
        public readonly Isbn $isbn,
        private int $totalCopies,
        private int $availableCopies,
        private ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->rename($title);

        if (
            $totalCopies < 1 ||
            $availableCopies < 0 ||
            $availableCopies > $totalCopies
        ) {
            throw new DomainException('Las existencias no son válidas.');
        }
    }

    public function title(): string
    {
        return $this->title;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function rename(string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            throw new DomainException('El título es obligatorio.');
        }

        $this->title = $title;
    }

    public function withdraw(): void
    {
        if ($this->deletedAt !== null) {
            throw new DomainException('El libro está dado de baja.');
        }

        if ($this->availableCopies === 0) {
            throw new DomainException('No quedan ejemplares disponibles.');
        }

        $this->availableCopies--;
    }

    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new DomainException(
                'Los disponibles no pueden superar el total.'
            );
        }

        $this->availableCopies++;
    }

    public function changeTotal(int $newTotal): void
    {
        $newAvailable = $this->availableCopies
            + ($newTotal - $this->totalCopies);

        if ($newTotal < 1 || $newAvailable < 0) {
            throw new DomainException(
                'El total no puede ser menor que los ejemplares prestados.'
            );
        }

        $this->totalCopies = $newTotal;
        $this->availableCopies = $newAvailable;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->availableCopies !== $this->totalCopies) {
            throw new DomainException(
                'No se puede dar de baja un libro con préstamos abiertos.'
            );
        }

        $this->deletedAt = $now;
    }
}