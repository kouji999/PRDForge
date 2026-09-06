<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\Contracts\AiProviderContract;
use App\Domain\Ai\Observability\AiLogger;
use App\Domain\Ai\Support\AiRequest;
use App\Domain\Ai\Support\AiResponse;
use App\Domain\Ai\Support\ErrorNormalizer;
use App\Models\Project;
use App\Models\User;

/**
 * Facade over provider adapters: adds observability, JSON validation and
 * combo failover. All AI operations in the app go through this service.
 *
 * A project may have an AI combo — an ordered team of providers. Requests
 * walk the chain: primary first, next member on failure. Without a combo,
 * the single default provider is used (same behavior as before).
 */
class AiService
{
    public function __construct(
        private readonly ProviderResolver $resolver,
    ) {}

    public function chat(User $user, AiRequest $request, string $operation = 'chat', ?Project $project = null): AiResponse
    {
        $chain = $this->resolver->resolveChain($user, $project);

        if ($chain === []) {
            throw ProviderNotConfiguredException::because('Belum ada AI provider yang dikonfigurasi.');
        }

        $lastError = null;
        $attempt = 0;

        foreach ($chain as $provider) {
            $attempt++;
            $requestId = AiLogger::requestId();
            $usedViaStream = false;

            try {
                $response = $provider->chat($request);

                AiLogger::logUsage(
                    requestId: $requestId,
                    providerName: $provider->name(),
                    model: 'configured',
                    operation: $operation.($attempt > 1 ? ':failover'.$attempt : ''),
                    status: 'success',
                    userId: $user->id,
                    inputTokens: $response->inputTokens,
                    outputTokens: $response->outputTokens,
                    latencyMs: $response->latencyMs,
                );

                return $response;
            } catch (AiProviderException $e) {
                $this->logError($provider, $requestId, $user, $operation, $e->category);
                $lastError = $e;

                // One in-provider stream retry first (buffered hangs),
                // then move to the next provider in the combo.
                if ($e->category === ErrorNormalizer::TIMEOUT) {
                    try {
                        $response = $provider->chatViaStream($request);

                        AiLogger::logUsage(
                            requestId: $requestId,
                            providerName: $provider->name(),
                            model: 'configured',
                            operation: $operation.':stream_retry',
                            status: 'success',
                            userId: $user->id,
                            latencyMs: $response->latencyMs,
                        );

                        return $response;
                    } catch (\Throwable $streamError) {
                        $this->logError($provider, $requestId, $user, $operation.':stream_retry', $this->errorCategory($streamError));
                        $lastError = $streamError;

                        continue;
                    }
                }

                if (in_array($e->category, $this->fatalCategories(), true)) {
                    throw $e;
                }

                continue;
            } catch (\Throwable $e) {
                $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($e));
                $lastError = $e;

                continue;
            }
        }

        throw $lastError ?? new AiProviderException('Semua provider di combo gagal.', 'provider_error');
    }

    /**
     * Chat expecting a validated JSON object response.
     * Uses streaming transport per provider (immune to buffered-response
     * hangs on flaky gateways), with combo failover.
     */
    public function chatJson(User $user, AiRequest $request, string $operation = 'chat', ?Project $project = null): array
    {
        $chain = $this->resolver->resolveChain($user, $project);

        if ($chain === []) {
            throw ProviderNotConfiguredException::because('Belum ada AI provider yang dikonfigurasi.');
        }

        $jsonRequest = $request->toJsonMode();
        $lastError = null;

        foreach ($chain as $provider) {
            $requestId = AiLogger::requestId();

            try {
                $response = $provider->chatViaStream($jsonRequest);

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
            } catch (AiProviderException $e) {
                $this->logError($provider, $requestId, $user, $operation, $e->category);
                $lastError = $e;

                if (in_array($e->category, $this->fatalCategories(), true)) {
                    throw $e;
                }

                continue;
            } catch (\Throwable $e) {
                $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($e));
                $lastError = $e;

                continue;
            }
        }

        throw $lastError ?? new AiProviderException('Semua provider di combo gagal.', 'provider_error');
    }

    /**
     * Streaming chat. Failover applies only until the first delta — once
     * tokens flow we commit to that provider (mid-stream switching would
     * corrupt the visible response).
     *
     * @return \Generator<string>
     */
    public function chatStream(User $user, AiRequest $request, string $operation = 'chat', ?Project $project = null): \Generator
    {
        $chain = $this->resolver->resolveChain($user, $project);

        if ($chain === []) {
            throw ProviderNotConfiguredException::because('Belum ada AI provider yang dikonfigurasi.');
        }

        $lastError = null;
        $providerIndex = 0;

        foreach ($chain as $provider) {
            $providerIndex++;
            $requestId = AiLogger::requestId();
            $buffer = '';
            $streamed = false;

            try {
                foreach ($provider->chatStream($request) as $delta) {
                    if ($buffer === '') {
                        AiLogger::logUsage(
                            requestId: $requestId,
                            providerName: $provider->name(),
                            model: 'configured',
                            operation: $operation.($providerIndex > 1 ? ':failover' : ''),
                            status: 'success',
                            userId: $user->id,
                        );
                    }

                    $buffer .= $delta;

                    yield $delta;
                }

                return;
            } catch (\Throwable $e) {
                // Before any token streamed → safe to failover.
                if (! $streamed) {
                    $this->logError($provider, $requestId, $user, $operation, $this->errorCategory($e));
                    $lastError = $e;

                    continue;
                }

                // Mid-stream failure: log and surface — content already shown.
                $this->logError($provider, $requestId, $user, $operation.':midstream', $this->errorCategory($e));

                throw $e;
            }
        }

        throw $lastError ?? new AiProviderException('Semua provider di combo gagal.', 'provider_error');
    }

    public function resolve(User $user): AiProviderContract
    {
        return $this->resolver->resolve($user);
    }

    public function parseJson(string $content): array
    {
        $decoded = $this->tryDecode($content);

        if ($decoded === null) {
            throw new AiProviderException('AI output bukan JSON valid.', ErrorNormalizer::INVALID_OUTPUT);
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

        // 2. Every balanced {...} substring — later candidates win
        //    (prose/thinking precedes the real payload).
        $candidates = $this->balancedObjects($content);

        foreach (array_reverse($candidates) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Truncated JSON repair (stream cut mid-emission)
        if ($candidates === [] && preg_match_all('/\{/', $content, $opens, PREG_OFFSET_CAPTURE)) {
            $lastOpen = end($opens[0]);
            $tail = substr($content, (int) $lastOpen[1]);

            if ($repaired = $this->repairTruncated($tail)) {
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

    /** @return list<ErrorNormalizer::*> */
    private function fatalCategories(): array
    {
        return [
            ErrorNormalizer::INVALID_KEY,
            ErrorNormalizer::INVALID_URL,
            ErrorNormalizer::INVALID_MODEL,
        ];
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

    private function errorCategory(\Throwable $e): string
    {
        if ($e instanceof AiProviderException) {
            return $e->category;
        }

        return 'unknown';
    }
}
