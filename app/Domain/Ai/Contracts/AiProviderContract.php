<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Support\AiRequest;
use App\Domain\Ai\Support\AiResponse;
use App\Domain\Ai\Support\ConnectionTestResult;

interface AiProviderContract
{
    /** Non-streaming chat completion. */
    public function chat(AiRequest $request): AiResponse;

    /**
     * Streaming chat completion. Yields string deltas.
     *
     * @return \Generator<string>
     */
    public function chatStream(AiRequest $request): \Generator;

    /** Validate configuration + connectivity. Never throws. */
    public function testConnection(): ConnectionTestResult;

    /** List available models from provider API, if supported. */
    public function listModels(): array;

    /** Provider identifier. */
    public function name(): string;
}
