<?php

namespace App\Domain\Ai\Support;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly int $latencyMs = 0,
        public readonly ?string $finishReason = null,
    ) {}
}
