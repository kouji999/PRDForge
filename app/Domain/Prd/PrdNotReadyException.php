<?php

namespace App\Domain\Prd;

use RuntimeException;

class PrdNotReadyException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
