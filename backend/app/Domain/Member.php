<?php

namespace App\Domain;

use InvalidArgumentException;

final readonly class Member
{
    public string $fullName;
    public string $documentNumber;

    public function __construct(
        public string $id,
        string $fullName,
        string $documentNumber,
    ) {
        $fullName = trim($fullName);
        $documentNumber = trim($documentNumber);

        if ($fullName === '' || $documentNumber === '') {
            throw new InvalidArgumentException(
                'El nombre y el documento son obligatorios.'
            );
        }

        $this->fullName = $fullName;
        $this->documentNumber = $documentNumber;
    }
}