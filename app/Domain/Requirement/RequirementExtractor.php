<?php

namespace App\Domain\Requirement;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use App\Domain\Ai\Observability\AiLogger;
use App\Domain\Ai\Prompts\PromptLibrary;
use App\Domain\Ai\Support\AiRequest;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\ProjectContext;
use App\Models\Requirement;
use App\Models\User;

/**
 * Turns conversation content into structured project context + requirements.
 * AI output is proposal-only: user confirms via status transitions.
 */
class RequirementExtractor
{
    public function __construct(
        private readonly AiService $ai,
    ) {}

    public function extractFromConversation(User $user, Conversation $conversation): ExtractionResult
    {
        $messages = $conversation->latestMessages(60)
            ->map(fn ($m) => ['role' => $m->role === 'assistant' ? 'assistant' : 'user', 'content' => $m->content])
            ->all();

        $request = new AiRequest(
            messages: $messages,
            systemPrompt: PromptLibrary::extractionSystem(),
            temperature: 0.2,
            maxTokens: 6000,
            jsonMode: true,
            timeoutSeconds: 600,
        );

        $generation = AiLogger::beginGeneration($user->id, 'extraction', $conversation->project_id);

        try {
            $data = $this->ai->chatJson($user, $request, 'extraction');

            $result = $this->apply($conversation->project, $data);

            AiLogger::completeGeneration($generation, ['request_id' => AiLogger::requestId()]);

            return $result;
        } catch (\Throwable $e) {
            AiLogger::failGeneration(
                $generation,
                $e instanceof AiProviderException ? $e->category : 'unknown',
                ['request_id' => AiLogger::requestId()],
            );

            throw $e;
        }
    }

    /** @param array<string, mixed> $data validated-ish payload from AI */
    public function apply(Project $project, array $data): ExtractionResult
    {
        $context = $project->ensureContext();

        $changes = $this->applyContext($context, $data);

        $requirements = $this->applyRequirements($project, $data['requirements'] ?? []);

        return new ExtractionResult(
            context: $context->fresh(),
            contextChanges: $changes,
            proposedRequirements: $requirements,
        );
    }

    /** Merge AI extraction into context — never overwrite user-confirmed fields with null. */
    private function applyContext(ProjectContext $context, array $data): array
    {
        $changes = [];

        $stringFields = [
            'problem' => 'problem',
            'target_users' => 'target_users',
            'product_concept' => 'product_concept',
            'platform' => 'platform',
            'constraints' => 'constraints',
            'mvp_scope' => 'mvp_scope',
        ];

        foreach ($stringFields as $field => $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '' && $value !== $context->{$field}) {
                $context->{$field} = $value;
                $changes[] = $field;
            }
        }

        $listFields = ['core_features', 'goals'];

        foreach ($listFields as $field) {
            $value = $data[$field] ?? null;

            if (is_array($value) && count($value) > 0) {
                $context->{$field} = array_values(array_filter(
                    array_map(fn ($v) => is_string($v) ? trim($v) : null, $value),
                    fn ($v) => $v !== null && $v !== '',
                ));
                $changes[] = $field;
            }
        }

        $context->ai_extracted_at = now();
        $context->save();

        return $changes;
    }

    /** @return Requirement[] proposed (deduplicated) requirements */
    private function applyRequirements(Project $project, array $raw): array
    {
        $existing = $project->requirements()
            ->withTrashed()
            ->get()
            ->keyBy(fn ($r) => mb_strtolower(trim($r->title)));

        $created = [];

        foreach (array_slice($raw, 0, 15) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $dedupeKey = mb_strtolower($title);

            if ($existing->has($dedupeKey)) {
                continue;
            }

            $requirement = $project->requirements()->create([
                'type' => in_array($item['type'] ?? '', ['functional', 'non_functional']) ? $item['type'] : 'functional',
                'title' => mb_substr($title, 0, 190),
                'content' => mb_substr((string) ($item['content'] ?? $title), 0, 2000),
                'status' => 'proposed',
                'source' => 'extracted',
                'priority' => in_array($item['priority'] ?? '', ['low', 'medium', 'high', 'critical']) ? $item['priority'] : 'medium',
            ]);

            $created[] = $requirement;
            $existing->put($dedupeKey, $requirement);
        }

        return $created;
    }
}
