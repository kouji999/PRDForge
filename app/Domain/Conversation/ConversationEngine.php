<?php

namespace App\Domain\Conversation;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use App\Domain\Ai\ContextBuilder;
use App\Domain\Ai\Observability\AiLogger;
use App\Domain\Ai\Prompts\PromptLibrary;
use App\Domain\Ai\ProviderNotConfiguredException;
use App\Domain\Ai\Support\AiRequest;
use App\Domain\Requirement\ReadinessEngine;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/**
 * Conversation engine: context-aware chat with streaming support.
 */
class ConversationEngine
{
    private const MAX_USER_MESSAGE_LENGTH = 8000;

    public function __construct(
        private readonly AiService $ai,
        private readonly ContextBuilder $contextBuilder,
        private readonly ReadinessEngine $readiness,
    ) {}

    public function addUserMessage(Conversation $conversation, string $content): Message
    {
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => mb_substr(trim($content), 0, self::MAX_USER_MESSAGE_LENGTH),
        ]);

        $conversation->update([
            'message_count' => $conversation->messages()->count(),
            'last_message_at' => now(),
            'title' => $conversation->title ?? mb_substr(trim($content), 0, 60),
        ]);

        return $message;
    }

    /**
     * Generate assistant reply (non-streaming) with full context assembly.
     */
    public function reply(User $user, Conversation $conversation): Message
    {
        $context = $this->contextBuilder->forConversation($conversation);
        $project = $conversation->project;

        $request = new AiRequest(
            messages: $context['messages'],
            systemPrompt: PromptLibrary::conversationSystem(
                projectName: $project->name,
                contextBlock: $context['systemContext'],
                requirementsBlock: null,
                prdSummary: $this->contextBuilder->prdSummary($project).$this->systemStatusBlock($project),
            ),
            temperature: 0.7,
            maxTokens: 8000,
        );

        $generation = AiLogger::beginGeneration($user->id, 'conversation', $project->id);

        try {
            $response = $this->ai->chat($user, $request, 'chat', $conversation->project);

            $message = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $response->content,
                'meta' => [
                    'provider' => $this->ai->resolve($user)->name(),
                    'latency_ms' => $response->latencyMs,
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'prompt_version' => PromptLibrary::PROMPT_VERSION,
                ],
            ]);

            $conversation->update([
                'message_count' => $conversation->messages()->count(),
                'last_message_at' => now(),
            ]);

            AiLogger::completeGeneration($generation, [
                'latency_ms' => $response->latencyMs,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'request_id' => AiLogger::requestId(),
            ]);

            return $message;
        } catch (\Throwable $e) {
            AiLogger::failGeneration(
                $generation,
                $e instanceof AiProviderException ? $e->category : 'unknown',
                ['request_id' => AiLogger::requestId()],
            );

            throw $e;
        }
    }

    /**
     * Stream assistant reply. Persists message after stream completes.
     *
     * @return \Generator<string> deltas; final persisted Message available via $onComplete callback
     */
    public function streamReply(User $user, Conversation $conversation, ?\Closure $onComplete = null): \Generator
    {
        $context = $this->contextBuilder->forConversation($conversation);
        $project = $conversation->project;

        $request = new AiRequest(
            messages: $context['messages'],
            systemPrompt: PromptLibrary::conversationSystem(
                projectName: $project->name,
                contextBlock: $context['systemContext'],
                requirementsBlock: null,
                prdSummary: $this->contextBuilder->prdSummary($project).$this->systemStatusBlock($project),
            ),
            temperature: 0.7,
            maxTokens: 8000,
            timeoutSeconds: 240, // per-provider: no token in 4 min = dead, combo fails over to next
        );

        $full = '';
        $providerName = 'unknown';

        try {
            $provider = $this->ai->resolve($user);
            $providerName = $provider->name();
        } catch (ProviderNotConfiguredException) {
            throw ProviderNotConfiguredException::because('Belum ada AI provider yang dikonfigurasi.');
        }

        $generation = AiLogger::beginGeneration($user->id, 'conversation', $project->id);
        $started = microtime(true);

        try {
            foreach ($this->ai->chatStream($user, $request, 'chat', $conversation->project) as $delta) {
                $full .= $delta;
                yield $delta;
            }
        } catch (\Throwable $e) {
            // Mid-stream death: never lose what was already generated.
            if (trim($full) !== '') {
                $this->persistReply($conversation, $full, $providerName, $started, partial: true);
            }

            AiLogger::failGeneration(
                $generation,
                $e instanceof AiProviderException ? $e->category : 'unknown',
                ['request_id' => AiLogger::requestId()],
            );

            throw $e;
        }

        if (trim($full) === '') {
            AiLogger::failGeneration($generation, 'empty_response', ['request_id' => AiLogger::requestId()]);
            throw new AiProviderException('AI mengembalikan respons kosong.', 'invalid_output');
        }

        $message = $this->persistReply($conversation, $full, $providerName, $started, partial: false);

        AiLogger::completeGeneration($generation, [
            'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            'request_id' => AiLogger::requestId(),
        ]);

        if ($onComplete) {
            $onComplete($message);
        }
    }

    /** Persist a streamed assistant reply (complete or partial). */
    /** Readiness/system status appended to the conversation system prompt. */
    private function systemStatusBlock(Project $project): string
    {
        $report = $this->readiness->evaluate($project);

        return "\n\n## Status Sistem (WAJIB diketahui)\n"
            .'Readiness: '.$report->score.'% ('.($report->ready ? 'READY' : 'BELUM READY').')'
            .($report->missing ? "\nInfo kurang: ".implode(', ', $report->missing) : '')
            ."\nTombol Generate PRD selalu bisa diklik user (ada konfirmasi bila belum ready)."
            .' Kalau user minta PRD langsung: dukung — arahkan klik Generate PRD (bisa dipaksa walau belum ready, bagian kosong diisi best practice).'
            .' PRD utuh HANYA dibuat lewat tombol itu; di chat, layani diskusi/outline per topik (maks ~600 kata per jawaban).'
            .' Jangan menyuruh "lengkapi dulu semua" secara mutlak.';
    }

    private function persistReply(Conversation $conversation, string $full, string $providerName, float $started, bool $partial): Message
    {
        $content = $full;

        if ($partial) {
            $content .= "\n\n_(respons terputus — ketuk ↻ di pesan untuk lanjut)_";
        }

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'meta' => [
                'provider' => $providerName,
                'latency_ms' => (int) ((microtime(true) - $started) * 1000),
                'prompt_version' => PromptLibrary::PROMPT_VERSION,
                'partial' => $partial,
            ],
        ]);

        $conversation->update([
            'message_count' => $conversation->messages()->count(),
            'last_message_at' => now(),
        ]);

        return $message;
    }
}
