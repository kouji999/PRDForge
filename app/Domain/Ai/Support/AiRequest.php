<?php

namespace App\Domain\Ai\Support;

class AiRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $systemPrompt = null,
        public readonly ?array $jsonSchema = null,
        public readonly float $temperature = 0.7,
        public readonly int $maxTokens = 4096,
        public readonly bool $jsonMode = false,
        public readonly int $timeoutSeconds = 120,
    ) {}

    public function toPayload(): array
    {
        $messages = [];

        if ($this->systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }

        foreach ($this->messages as $message) {
            $messages[] = $message;
        }

        $payload = [
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stream' => false,
        ];

        if ($this->jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    /** Clone request with JSON response mode forced on. */
    public function toJsonMode(): self
    {
        return new self(
            messages: $this->messages,
            systemPrompt: $this->systemPrompt,
            jsonSchema: $this->jsonSchema,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            jsonMode: true,
            timeoutSeconds: $this->timeoutSeconds,
        );
    }

    public function toStreamPayload(): array
    {
        $payload = $this->toPayload();
        $payload['stream'] = true;

        return $payload;
    }
}
