<?php

namespace App\Domain\Requirement;

use App\Domain\Ai\AiService;
use App\Domain\Ai\ContextBuilder;
use App\Domain\Ai\Prompts\PromptLibrary;
use App\Domain\Ai\Support\AiRequest;
use App\Models\Project;
use App\Models\User;

/**
 * Criteria-based readiness gate. Never random — deterministic local check
 * first, AI enriches with notes + missing questions when project is close.
 */
class ReadinessEngine
{
    /** @var list<array{key: string, label: string}> */
    public const CRITERIA = [
        ['key' => 'problem', 'label' => 'Problem Statement'],
        ['key' => 'target_users', 'label' => 'Target Users'],
        ['key' => 'product_concept', 'label' => 'Product Concept'],
        ['key' => 'core_features', 'label' => 'Core Features'],
        ['key' => 'user_journey', 'label' => 'User Journey'],
        ['key' => 'mvp_scope', 'label' => 'MVP Scope'],
    ];

    public function __construct(
        private readonly AiService $ai,
    ) {}

    public function evaluate(Project $project): ReadinessReport
    {
        $context = $project->ensureContext();

        $criteria = collect(self::CRITERIA)
            ->map(function (array $c) use ($context, $project) {
                $value = $context->{$c['key']};

                $met = match ($c['key']) {
                    'core_features' => is_array($value) && count($value) >= 2,
                    'user_journey' => $this->journeyEvidence($project),
                    default => is_string($value) && mb_strlen(trim($value)) >= 20,
                };

                return [
                    'key' => $c['key'],
                    'label' => $c['label'],
                    'status' => $met ? 'met' : 'missing',
                    'note' => null,
                ];
            })
            ->values()
            ->all();

        $metCount = count(array_filter($criteria, fn ($c) => $c['status'] === 'met'));
        $score = (int) round(($metCount / count(self::CRITERIA)) * 100);
        $ready = $score >= 83; // 5/6 minimum

        return new ReadinessReport(
            ready: $ready,
            score: $score,
            criteria: $criteria,
            missing: array_values(array_map(
                fn ($c) => $c['label'],
                array_filter($criteria, fn ($c) => $c['status'] === 'missing'),
            )),
        );
    }

    /** User journey is implied by ≥1 confirmed requirement or MVP scope text. */
    private function journeyEvidence(Project $project): bool
    {
        $context = $project->ensureContext();

        if (is_string($context->mvp_scope) && mb_strlen(trim($context->mvp_scope)) >= 20) {
            return true;
        }

        return $project->requirements()
            ->where('status', 'confirmed')
            ->exists();
    }

    /** AI-enriched analysis: what's missing and what to ask next. */
    public function analyzeWithAi(User $user, Project $project): ReadinessReport
    {
        $base = $this->evaluate($project);

        if ($base->ready) {
            return $base;
        }

        $request = new AiRequest(
            messages: [[
                'role' => 'user',
                'content' => "Konteks project:\n".app(ContextBuilder::class)->contextBlock($project),
            ]],
            systemPrompt: PromptLibrary::readinessSystem(),
            temperature: 0.2,
            maxTokens: 3000,
            jsonMode: true,
        );

        try {
            $data = $this->ai->chatJson($user, $request, 'readiness');

            $base = $this->mergeAiNotes($base, $data);
        } catch (\Throwable) {
            // AI analysis is optional enrichment; local evaluation remains authoritative.
        }

        return $base;
    }

    private function mergeAiNotes(ReadinessReport $base, array $data): ReadinessReport
    {
        $criteria = $base->criteria;

        $aiCriteria = collect($data['criteria'] ?? [])
            ->filter(fn ($c) => is_array($c) && isset($c['key']))
            ->keyBy(fn ($c) => $c['key']);

        foreach ($criteria as $i => $criterion) {
            $ai = $aiCriteria->get($criterion['key']);

            if ($ai && in_array($ai['status'] ?? '', ['met', 'partial', 'missing'])) {
                $status = $criterion['status'] === 'met' ? 'met' : $ai['status'];

                $criteria[$i]['status'] = $status;
                $criteria[$i]['note'] = is_string($ai['note'] ?? null) ? mb_substr($ai['note'], 0, 300) : null;
            }
        }

        $missingItems = collect($data['missing_items'] ?? [])
            ->filter(fn ($v) => is_string($v))
            ->take(6)
            ->values()
            ->all();

        return new ReadinessReport(
            ready: $base->ready,
            score: $base->score,
            criteria: $criteria,
            missing: $missingItems ?: $base->missing,
        );
    }
}
