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
        } catch (\Throwable $e) {
            $category = $this->errorCategory($e);

            AiLogger::logUsage(
                requestId: $requestId,
                providerName: $provider->name(),
                model: 'configured',
                operation: $operation,
                status: 'error',
                userId: $user->id,
                errorCategory: $category,
            );

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

    /**
     * Chat expecting a validated JSON object response.
     * Tolerates: markdown fences, prose around JSON, reasoning-model rambling.
     */
    public function chatJson(User $user, AiRequest $request, string $operation = 'chat'): array
    {
        $response = $this->chat($user, $request->toJsonMode(), $operation);

        return $this->parseJson($response->content);
    }

    public function parseJson(string $content): array
    {
        $decoded = $this->tryDecode($content);

        if ($decoded === null) {
            throw new AiProviderException('AI output bukan JSON valid.', 'invalid_output');
        }

        return $decoded;
    }

    /** Decode JSON leniently: fences → first {...} block → raw. */
    private function tryDecode(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        // 1. Direct decode
        $decoded = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 2. Strip markdown fences, retry
        $stripped = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $stripped = preg_replace('/\s*```$/', '', $stripped) ?? $stripped;

        $decoded = json_decode(trim($stripped), true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 3. First balanced {...} or [...] block anywhere in text
        $first = strpos($content, '{');
        $last = strrpos($content, '}');

        if ($first !== false && $last !== false && $last > $first) {
            $candidate = substr($content, $first, $last - $first + 1);
            $decoded = json_decode($candidate, true);

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
