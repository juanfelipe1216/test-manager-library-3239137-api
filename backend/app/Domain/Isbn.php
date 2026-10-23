<?php

namespace App\Domain;

use InvalidArgumentException;

final readonly class Isbn
{
    public string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^[0-9]{13}$/D', $value)) {
            throw new InvalidArgumentException(
                'El ISBN debe contener exactamente 13 dígitos.'
            );
        }

        $this->value = $value;
    }
}