<?php

namespace App\Domain\Ai\Support;

class ConnectionTestResult
{
    public function __construct(
        public readonly bool $connected,
        public readonly string $message,
        public readonly ?string $errorCategory = null,
        public readonly int $latencyMs = 0,
        public readonly ?array $models = null,
    ) {}

    public static function success(int $latencyMs, ?array $models = null): self
    {
        return new self(true, 'Connected successfully.', null, $latencyMs, $models);
    }

    public static function failure(string $category, string $message): self
    {
        return new self(false, $message, $category);
    }
}
