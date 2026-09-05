<?php

namespace App\Domain\Ai;

use RuntimeException;

class ProviderNotConfiguredException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
