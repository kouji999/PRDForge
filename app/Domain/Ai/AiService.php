<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\Contracts\AiProviderContract;
use App\Domain\Ai\Observability\AiLogger;
use App\Domain\Ai\Support\AiRequest;
use App\Domain\Ai\Support\AiResponse;
use App\Models\User;

/**
 * Facade over provider adapters: adds observability + JSON validation.
 * All AI operations in the app go through this service.
 */
class AiService
{
    public function __construct(
        private readonly ProviderResolver $resolver,
    ) {}

    public function chat(User $user, AiRequest $request, string $operation = 'chat'): AiResponse
    {
        $provider = $this->resolver->resolve($user);
        $requestId = AiLogger::requestId();

        try {
            $response = $provider->chat($request);

            AiLogger::logUsage(
                requestId: $requestId,
                providerName: $provider->name(),
                model: 'configured',
                operation: $operation,
                status: 'success',
                userId: $user->id,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                latencyMs: $response->latencyMs,
            );

            return $response;
        } catch (AiProviderException $e) {
            if ($e->category === ErrorNormalizer::TIMEOUT) {
                // Gateway hung the non-streaming request. Retry once over
                // SSE transport — immune to whole-response buffering stalls.
                try {
                    $response = $provider->chatViaStream($request);

                    AiLogger::logUsage(
                        requestId: $requestId,
                        providerName: $provider->name(),
                        model: 'configured',
                        operation: $operation.':stream_fallback',
                        status: 'success',
                        userId: $user->id,
                        latencyMs: $response->latencyMs,
                    );

                    return $response;
                } catch (\Throwable $fallbackError) {
                    $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($fallbackError));

                    throw $fallbackError;
                }
            }

            $this->logError($provider, $requestId, $user, $operation, $e->category);

            throw $e;
        } catch (\Throwable $e) {
            $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($e));

            throw $e;
        }
    }

    /**
     * Chat expecting a validated JSON object response.
     * Tolerates: markdown fences, prose around JSON, reasoning-model rambling.
     * Uses streaming transport directly for JSON ops — reasoning models on
     * flaky gateways deliver SSE reliably but hang buffered responses.
     */
    public function chatJson(User $user, AiRequest $request, string $operation = 'chat'): array
    {
        $provider = $this->resolver->resolve($user);
        $requestId = AiLogger::requestId();

        try {
            $response = $provider->chatViaStream($request->toJsonMode());

            AiLogger::logUsage(
                requestId: $requestId,
                providerName: $provider->name(),
                model: 'configured',
                operation: $operation,
                status: 'success',
                userId: $user->id,
                latencyMs: $response->latencyMs,
            );

            return $this->parseJson($response->content);
        } catch (\Throwable $e) {
            $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($e));

            throw $e;
        }
    }

    /**
     * @return \Generator<string>
     */
    public function chatStream(User $user, AiRequest $request, string $operation = 'chat'): \Generator
    {
        $provider = $this->resolver->resolve($user);

        yield from $provider->chatStream($request);
    }

    private function logError(AiProviderContract $provider, string $requestId, User $user, string $operation, string $category): void
    {
        AiLogger::logUsage(
            requestId: $requestId,
            providerName: $provider->name(),
            model: 'configured',
            operation: $operation,
            status: 'error',
            userId: $user->id,
            errorCategory: $category,
        );
    }

    public function parseJson(string $content): array
    {
        $decoded = $this->tryDecode($content);

        if ($decoded === null) {
            throw new AiProviderException('AI output bukan JSON valid.', 'invalid_output');
        }

        return $decoded;
    }

    /**
     * Decode JSON leniently. Handles: clean JSON, markdown fences, prose
     * around JSON, reasoning-model thinking before JSON, truncated tails.
     */
    private function tryDecode(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        // 1. Direct / fenced decode
        foreach ([$content, $this->stripFences($content)] as $candidate) {
            $decoded = json_decode(trim($candidate), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 2. Extract every balanced {...} substring and try it — later
        //    candidates win (prose/thinking comes before the real payload).
        $candidates = $this->balancedObjects($content);

        foreach (array_reverse($candidates) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Truncated JSON repair (stream cut mid-emission) — work from
        //    the LAST unbalanced opener: everything after it is the payload.
        if ($candidates === [] && preg_match_all('/\{/', $content, $opens, PREG_OFFSET_CAPTURE)) {
            $lastOpen = end($opens[0]);
            $tail = substr($content, (int) $lastOpen[1]);
            $content2 = $this->stripFences($tail);

            if ($repaired = $this->repairTruncated($content2)) {
                return $repaired;
            }
        }

        return null;
    }

    private function stripFences(string $content): string
    {
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;

        return preg_replace('/\s*```\s*$/', '', $stripped) ?? $stripped;
    }

    /**
     * All balanced {...} substrings (single-pass depth scanner, quote-aware).
     *
     * @return list<string>
     */
    private function balancedObjects(string $content): array
    {
        $objects = [];
        $len = strlen($content);
        $stack = [];
        $inString = false;
        $escaped = false;
        $start = null;

        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                if ($stack === []) {
                    $start = $i;
                }
                $stack[] = $i;
            } elseif ($char === '}') {
                if ($stack !== []) {
                    array_pop($stack);

                    if ($stack === [] && $start !== null) {
                        $objects[] = substr($content, $start, $i - $start + 1);
                        $start = null;
                    }
                }
            }
        }

        return $objects;
    }

    /** Attempt common tail repairs for a truncated JSON candidate. */
    private function repairTruncated(string $candidate): ?array
    {
        $trimmed = rtrim($candidate, " \t\r\n,`");

        foreach (['}', ']}', '"}', '"]', '"]}', '"}]}', '"]}', '"}}', '}]}'] as $tail) {
            $decoded = json_decode($trimmed.$tail, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public function resolve(User $user): AiProviderContract
    {
        return $this->resolver->resolve($user);
    }

    private function errorCategory(\Throwable $e): string
    {
        if ($e instanceof AiProviderException) {
            return $e->category;
        }

        return 'unknown';
    }
}
