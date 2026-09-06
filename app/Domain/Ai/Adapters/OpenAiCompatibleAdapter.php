<?php

namespace App\Domain\Ai\Adapters;

use App\Domain\Ai\Contracts\AiProviderContract;
use App\Domain\Ai\Support\AiRequest;
use App\Domain\Ai\Support\AiResponse;
use App\Domain\Ai\Support\ConnectionTestResult;
use App\Domain\Ai\Support\ErrorNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Single adapter for all OpenAI-compatible providers (9Router proxy, OpenAI,
 * Groq, OpenRouter, custom gateways). Differences live only in configuration.
 */
class OpenAiCompatibleAdapter implements AiProviderContract
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?int $providerId = null,
        private readonly string $providerName = 'openai-compatible',
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $started = microtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($request->timeoutSeconds)
                ->connectTimeout(10)
                ->post($this->endpoint('/chat/completions'), $request->toPayload() + ['model' => $this->model]);
        } catch (ConnectionException $e) {
            throw $this->wrap($e);
        }

        if ($response->failed()) {
            throw $this->wrapWithStatus($response->status(), $response->body());
        }

        $data = $response->json();

        if (! isset($data['choices'][0]['message'])) {
            throw new AiProviderException('Malformed response: missing choices[0].message', ErrorNormalizer::INVALID_OUTPUT);
        }

        $message = $data['choices'][0]['message'];
        $content = $message['content'] ?? null;
        $reasoning = $message['reasoning_content'] ?? null;

        // Reasoning models may return empty content with reasoning only.
        if (! is_string($content) || trim($content) === '') {
            if (is_string($reasoning) && trim($reasoning) !== '') {
                $content = $reasoning;
            } else {
                throw new AiProviderException('Provider returned empty content.', ErrorNormalizer::INVALID_OUTPUT);
            }
        }

        $usage = $data['usage'] ?? [];

        return new AiResponse(
            content: $content,
            inputTokens: $usage['prompt_tokens'] ?? null,
            outputTokens: $usage['completion_tokens'] ?? null,
            latencyMs: (int) ((microtime(true) - $started) * 1000),
            finishReason: $data['choices'][0]['finish_reason'] ?? null,
        );
    }

    /**
     * @return \Generator<string> content deltas (UI display) — reasoning excluded
     */
    public function chatStream(AiRequest $request): \Generator
    {
        yield from $this->streamDeltas($request, includeReasoning: false);
    }

    /**
     * @return \Generator<string> content+reasoning deltas — accumulated
     *                            server-side, reasoning reappears as final content for
     *                            reasoning-only responses (JSON ops tolerate it via parser).
     */
    public function chatViaStream(AiRequest $request): AiResponse
    {
        $started = microtime(true);
        $full = '';

        foreach ($this->streamDeltas($request, includeReasoning: true) as $delta) {
            $full .= $delta;
        }

        if (trim($full) === '') {
            throw new AiProviderException('Provider returned empty content.', ErrorNormalizer::INVALID_OUTPUT);
        }

        return new AiResponse(
            content: $full,
            latencyMs: (int) ((microtime(true) - $started) * 1000),
            finishReason: 'stop',
        );
    }

    /**
     * @return \Generator<string>
     */
    private function streamDeltas(AiRequest $request, bool $includeReasoning): \Generator
    {
        $body = $request->toStreamPayload() + ['model' => $this->model];

        try {
            $client = Http::withToken($this->apiKey)
                ->timeout($request->timeoutSeconds)
                ->connectTimeout(10)
                ->withOptions(['stream' => true]);

            $response = $client->post($this->endpoint('/chat/completions'), $body);

            if ($response->failed()) {
                throw $this->wrapWithStatus($response->status(), $response->body());
            }

            $contentType = (string) ($response->header('Content-Type') ?? '');

            // Gateways that ignore stream:true return a buffered JSON
            // completion — handle it directly.
            if (! str_contains($contentType, 'event-stream')) {
                $data = $response->json();

                if (isset($data['choices'][0]['message'])) {
                    $message = $data['choices'][0]['message'];
                    $content = $message['content'] ?? $message['reasoning_content'] ?? null;

                    if (is_string($content) && $content !== '') {
                        yield $content;

                        return;
                    }
                }

                throw new AiProviderException('Malformed response: missing choices[0].message', ErrorNormalizer::INVALID_OUTPUT);
            }

            $stream = $response->toPsrResponse()->getBody();

            $buffer = '';
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                $buffer .= $chunk;

                while (($lineEnd = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $lineEnd));
                    $buffer = substr($buffer, $lineEnd + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));

                    if ($data === '[DONE]') {
                        return;
                    }

                    $decoded = json_decode($data, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }

                    $choice = $decoded['choices'][0] ?? [];
                    $delta = $choice['delta']['content'] ?? null;
                    $reasoning = $choice['delta']['reasoning_content'] ?? null;

                    if (is_string($delta) && $delta !== '') {
                        yield $delta;
                    } elseif ($includeReasoning && is_string($reasoning) && $reasoning !== '') {
                        yield $reasoning;
                    }
                }
            }
        } catch (ConnectionException $e) {
            throw $this->wrap($e);
        }
    }

    public function testConnection(): ConnectionTestResult
    {
        $started = microtime(true);

        if (! preg_match('#^https?://#i', $this->baseUrl)) {
            return ConnectionTestResult::failure(ErrorNormalizer::INVALID_URL, ErrorNormalizer::message(ErrorNormalizer::INVALID_URL));
        }

        // Fast path: models endpoint proves URL + key without expensive generation.
        // Reasoning models burn whole token budgets thinking, so chat pings are unreliable.
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(20)
                ->connectTimeout(10)
                ->get($this->endpoint('/models'));

            $latency = (int) ((microtime(true) - $started) * 1000);

            if ($response->status() === 401 || $response->status() === 403) {
                return ConnectionTestResult::failure(ErrorNormalizer::INVALID_KEY, ErrorNormalizer::message(ErrorNormalizer::INVALID_KEY));
            }

            if ($response->status() === 404) {
                // Provider may not expose /models — fall through to chat probe.
                return $this->chatProbe($started);
            }

            if ($response->status() === 429) {
                return ConnectionTestResult::failure(ErrorNormalizer::RATE_LIMITED, ErrorNormalizer::message(ErrorNormalizer::RATE_LIMITED));
            }

            if ($response->failed()) {
                [$category, $message] = ErrorNormalizer::categorize(new \RuntimeException('http error'), $response->status());

                return ConnectionTestResult::failure($category, $message);
            }

            $models = collect($response->json('data') ?? [])
                ->pluck('id')
                ->filter(fn ($v) => is_string($v))
                ->values()
                ->all();

            return ConnectionTestResult::success($latency, $models);
        } catch (ConnectionException $e) {
            [$category, $message] = ErrorNormalizer::categorize($e);

            return ConnectionTestResult::failure($category, $message);
        }
    }

    /** Fallback probe via minimal chat completion (for gateways without /models). */
    private function chatProbe(float $started): ConnectionTestResult
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->connectTimeout(10)
                ->post($this->endpoint('/chat/completions'), [
                    'model' => $this->model,
                    'messages' => [['role' => 'user', 'content' => 'ping']],
                    'max_tokens' => 50,
                ]);

            $latency = (int) ((microtime(true) - $started) * 1000);

            if ($response->status() === 401) {
                return ConnectionTestResult::failure(ErrorNormalizer::INVALID_KEY, ErrorNormalizer::message(ErrorNormalizer::INVALID_KEY));
            }

            if ($response->status() === 400) {
                $body = mb_strtolower($response->body());

                if (str_contains($body, 'model') && (str_contains($body, 'not found') || str_contains($body, 'invalid'))) {
                    return ConnectionTestResult::failure(ErrorNormalizer::INVALID_MODEL, ErrorNormalizer::message(ErrorNormalizer::INVALID_MODEL));
                }
            }

            if ($response->failed()) {
                [$category, $message] = ErrorNormalizer::categorize(new \RuntimeException('http error'), $response->status());

                return ConnectionTestResult::failure($category, $message);
            }

            $data = $response->json();

            // Reasoning models may spend all tokens thinking; a valid
            // choices array already proves url+key+model are correct.
            if (! isset($data['choices'][0])) {
                return ConnectionTestResult::failure(ErrorNormalizer::PROVIDER_ERROR, ErrorNormalizer::message(ErrorNormalizer::PROVIDER_ERROR));
            }

            return ConnectionTestResult::success($latency);
        } catch (ConnectionException $e) {
            [$category, $message] = ErrorNormalizer::categorize($e);

            return ConnectionTestResult::failure($category, $message);
        }
    }

    public function listModels(): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(15)
                ->connectTimeout(10)
                ->get($this->endpoint('/models'));

            if ($response->failed()) {
                return [];
            }

            $data = $response->json();

            return collect($data['data'] ?? [])
                ->pluck('id')
                ->filter(fn ($v) => is_string($v))
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }

    private function wrap(ConnectionException $e): AiProviderException
    {
        [$category, $message] = ErrorNormalizer::categorize($e);

        return new AiProviderException($message, $category, $e);
    }

    private function wrapWithStatus(int $status, string $body): AiProviderException
    {
        [$category, $message] = ErrorNormalizer::categorize(new \RuntimeException(ErrorNormalizer::sanitize($body)), $status);

        return new AiProviderException($message, $category);
    }
}
