<?php

namespace App\Domain\Ai\Observability;

use App\Models\AiGeneration;
use App\Models\AiUsageLog;
use Illuminate\Support\Str;

/**
 * Logs AI operations for observability. Never stores: API keys,
 * auth headers, raw prompts.
 */
class AiLogger
{
    public static function requestId(): string
    {
        return (string) Str::uuid();
    }

    public static function logUsage(
        string $requestId,
        string $providerName,
        string $model,
        string $operation,
        string $status,
        ?int $userId = null,
        ?int $providerId = null,
        ?string $errorCategory = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?int $latencyMs = null,
        ?array $context = null,
    ): void {
        AiUsageLog::create([
            'request_id' => $requestId,
            'user_id' => $userId,
            'provider_id' => $providerId,
            'provider_name' => $providerName,
            'model' => $model,
            'operation' => $operation,
            'status' => $status,
            'error_category' => $errorCategory,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms' => $latencyMs,
            'context' => $context,
        ]);
    }

    public static function beginGeneration(
        int $userId,
        string $type,
        ?int $projectId = null,
        ?int $providerId = null,
    ): AiGeneration {
        return AiGeneration::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'provider_id' => $providerId,
            'type' => $type,
            'status' => 'pending',
        ]);
    }

    public static function completeGeneration(AiGeneration $generation, array $meta = []): void
    {
        $generation->update([
            'status' => 'completed',
            'latency_ms' => $meta['latency_ms'] ?? null,
            'meta' => [
                'model' => $meta['model'] ?? null,
                'input_tokens' => $meta['input_tokens'] ?? null,
                'output_tokens' => $meta['output_tokens'] ?? null,
                'request_id' => $meta['request_id'] ?? null,
            ],
        ]);
    }

    public static function failGeneration(AiGeneration $generation, string $errorCategory, array $meta = []): void
    {
        $generation->update([
            'status' => 'failed',
            'error_category' => $errorCategory,
            'latency_ms' => $meta['latency_ms'] ?? null,
            'meta' => [
                'model' => $meta['model'] ?? null,
                'request_id' => $meta['request_id'] ?? null,
            ],
        ]);
    }
}
