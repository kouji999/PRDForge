<?php

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Support\ErrorNormalizer;
use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $category = ErrorNormalizer::UNKNOWN,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
