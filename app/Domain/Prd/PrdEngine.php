<?php

namespace App\Domain\Prd;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use App\Domain\Ai\ContextBuilder;
use App\Domain\Ai\Observability\AiLogger;
use App\Domain\Ai\Prompts\PromptLibrary;
use App\Domain\Ai\Support\AiRequest;
use App\Models\Prd;
use App\Models\PrdSection;
use App\Models\PrdVersion;
use App\Models\User;

/**
 * PRD engine: section AI actions, review, apply-proposal, versioning.
 * Generation lives in App\Services\PrdGenerationService (queued chunks).
 */
class PrdEngine
{
    public function __construct(
        private readonly AiService $ai,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    /** AI action on a single section: returns proposal, never mutates directly. */
    public function sectionAction(User $user, Prd $prd, PrdSection $section, string $action): SectionActionProposal
    {
        $allowed = ['rewrite', 'expand', 'simplify', 'review', 'contradictions'];

        if (! in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown action: {$action}");
        }

        $request = new AiRequest(
            messages: [[
                'role' => 'user',
                'content' => "Konteks project:\n".$this->contextBuilder->contextBlock($prd->project)."\n\nSection saat ini:\n# {$section->title}\n{$section->content}",
            ]],
            systemPrompt: PromptLibrary::sectionActionSystem($action, $section->title),
            temperature: 0.4,
            maxTokens: 8000,
            jsonMode: true,
            timeoutSeconds: 600,
        );

        $generation = AiLogger::beginGeneration($user->id, 'section_action', $prd->project_id);

        try {
            $data = $this->ai->chatJson($user, $request, 'section_action', $prd->project);

            AiLogger::completeGeneration($generation, ['request_id' => AiLogger::requestId()]);

            return new SectionActionProposal(
                sectionId: $section->id,
                action: $action,
                content: (string) ($data['content'] ?? ''),
                changelog: (string) ($data['changelog'] ?? ''),
                original: $action === 'review' || $action === 'contradictions' ? null : $section->content,
            );
        } catch (\Throwable $e) {
            AiLogger::failGeneration(
                $generation,
                $e instanceof AiProviderException ? $e->category : 'unknown',
                ['request_id' => AiLogger::requestId()],
            );

            throw $e;
        }
    }

    /** Apply a user-confirmed proposal. Safety flow: propose → confirm → apply. */
    public function applyProposal(User $user, PrdSection $section, string $content): PrdSection
    {
        $section->update(['content' => $content]);

        return $section->fresh();
    }

    /** Full PRD AI review. */
    public function review(User $user, Prd $prd): PrdReviewResult
    {
        $request = new AiRequest(
            messages: [[
                'role' => 'user',
                'content' => $this->contextBuilder->prdFullBlock($prd),
            ]],
            systemPrompt: PromptLibrary::prdReviewSystem(),
            temperature: 0.2,
            maxTokens: 4000,
            jsonMode: true,
            timeoutSeconds: 600,
        );

        $generation = AiLogger::beginGeneration($user->id, 'prd_review', $prd->project_id);

        try {
            $data = $this->ai->chatJson($user, $request, 'prd_review', $prd->project);

            AiLogger::completeGeneration($generation, ['request_id' => AiLogger::requestId()]);

            return new PrdReviewResult(
                gaps: $this->stringList($data['gaps'] ?? []),
                contradictions: $this->stringList($data['contradictions'] ?? []),
                suggestions: $this->stringList($data['suggestions'] ?? []),
                verdict: in_array($data['verdict'] ?? '', ['pass', 'needs_work']) ? $data['verdict'] : 'needs_work',
            );
        } catch (\Throwable $e) {
            AiLogger::failGeneration(
                $generation,
                $e instanceof AiProviderException ? $e->category : 'unknown',
                ['request_id' => AiLogger::requestId()],
            );

            throw $e;
        }
    }

    /** Create immutable version snapshot. */
    public function createVersion(Prd $prd, ?string $label = null, ?string $version = null): PrdVersion
    {
        $latest = $prd->versions()->orderByDesc('id')->first();

        if ($version === null) {
            $version = $this->nextVersion($latest?->version, $label);
        }

        $snapshot = [
            'title' => $prd->title,
            'summary' => $prd->summary,
            'status' => $prd->status,
            'sections' => $prd->sections->map(fn (PrdSection $s) => [
                'key' => $s->key,
                'title' => $s->title,
                'content' => $s->content,
                'order' => $s->order,
                'status' => $s->status,
            ])->all(),
        ];

        return $prd->versions()->create([
            'version' => $version,
            'label' => $label,
            'snapshot' => $snapshot,
            'section_count' => count($snapshot['sections']),
        ]);
    }

    private function nextVersion(?string $latest, ?string $label): string
    {
        if (! $latest) {
            return 'v0.1';
        }

        if (str_contains(mb_strtolower((string) $label), 'approved')) {
            return 'v1.0';
        }

        if (preg_match('/v(\d+)\.(\d+)/', $latest, $m)) {
            return sprintf('v%d.%d', (int) $m[1], (int) $m[2] + 1);
        }

        return 'v0.2';
    }

    /** @return list<string> */
    private function stringList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($v) => is_string($v))
            ->map(fn ($s) => mb_substr($s, 0, 500))
            ->values()
            ->all();
    }
}
