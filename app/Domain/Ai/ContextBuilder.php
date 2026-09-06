<?php

namespace App\Domain\Ai;

use App\Models\Conversation;
use App\Models\Prd;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Support\Str;

/**
 * Assembles AI context with strict token budget. Never dumps the whole DB.
 */
class ContextBuilder
{
    /** Recent messages sent to AI verbatim. */
    private const RECENT_WINDOW = 12;

    /** Older messages summarized (keeps long chats inside reasoning budget). */
    private const SUMMARY_WINDOW = 20;

    /** Approximate char budget for the whole context block. */
    private const CHAR_BUDGET = 12000;

    /** Per-message char cap for recent turns. */
    private const MESSAGE_CHAR_CAP = 6000;

    public function forConversation(Conversation $conversation): array
    {
        $project = $conversation->project()->with('context')->firstOrFail();

        $all = $conversation->latestMessages(self::RECENT_WINDOW + self::SUMMARY_WINDOW);

        $split = $all->count() > self::RECENT_WINDOW
            ? $all->slice(0, $all->count() - self::RECENT_WINDOW)->values()
            : collect();

        $recent = $all->count() > self::RECENT_WINDOW
            ? $all->slice($all->count() - self::RECENT_WINDOW)->values()
            : $all;

        $messages = [];

        // Older turns become one compact digest block — preserves decisions
        // without flooding the reasoning budget with full history.
        if ($split->isNotEmpty()) {
            $digest = $split->map(function ($m) {
                $role = $m->role === 'assistant' ? 'AI' : 'User';

                return "- [{$role}] ".Str::limit(trim($m->content), 300);
            })->implode("\n");

            $messages[] = [
                'role' => 'user',
                'content' => "RIWAYAT DISKUSI SEBELUMNYA (ringkas — jangan diulang, cukup jadi konteks):\n{$digest}",
            ];
        }

        foreach ($recent as $m) {
            $messages[] = [
                'role' => $m->role === 'assistant' ? 'assistant' : 'user',
                'content' => Str::limit($m->content, self::MESSAGE_CHAR_CAP),
            ];
        }

        return [
            'messages' => $messages,
            'systemContext' => $this->contextBlock($project),
        ];
    }

    public function contextBlock(Project $project): string
    {
        $context = $project->context;
        $requirements = $project->requirements()
            ->whereIn('status', ['confirmed', 'needs_review'])
            ->orderBy('type')
            ->limit(30)
            ->get();

        $lines = [];

        // Full original brief — the richest source of user intent.
        $lines[] = $this->field('Brief Asli (dari user)', $project->description);

        if ($context) {
            $lines[] = $this->field('Masalah', $context->problem);
            $lines[] = $this->field('Target Users', $context->target_users);
            $lines[] = $this->field('Konsep Produk', $context->product_concept);
            $lines[] = $this->listField('Fitur Inti', $context->core_features);
            $lines[] = $this->field('Platform', $context->platform);
            $lines[] = $this->field('Constraints', $context->constraints);
            $lines[] = $this->listField('Goals', $context->goals);
            $lines[] = $this->field('MVP Scope', $context->mvp_scope);
        }

        if ($requirements->isNotEmpty()) {
            $reqLines = $requirements->map(fn (Requirement $r) => sprintf(
                '- [%s|%s|%s] %s: %s',
                $r->type,
                $r->priority,
                $r->status,
                $r->title,
                Str::limit($r->content, 200),
            ))->implode("\n");

            $lines[] = "### Requirements\n".$reqLines;
        }

        $block = implode("\n\n", array_filter($lines));

        return Str::limit($block, self::CHAR_BUDGET, "\n…[context truncated]");
    }

    public function prdSummary(Project $project): string
    {
        $prd = $project->prd()->with('sections')->first();

        if (! $prd) {
            return 'PRD belum dibuat.';
        }

        $sectionList = $prd->sections
            ->take(25)
            ->map(fn ($s) => "- {$s->title} ({$s->status})")
            ->implode("\n");

        return sprintf(
            "PRD \"%s\" (status: %s, %d sections):\n%s",
            $prd->title,
            $prd->status,
            $prd->sections->count(),
            $sectionList,
        );
    }

    public function prdFullBlock(Prd $prd): string
    {
        $sections = $prd->sections->map(fn ($s) => sprintf(
            "## %s\n%s",
            $s->title,
            Str::limit($s->content, 900),
        ))->implode("\n\n");

        return Str::limit(
            "Konteks:\n".$this->contextBlock($prd->project)."\n\nPRD: {$prd->title}\n{$prd->summary}\n\n{$sections}",
            self::CHAR_BUDGET,
            "\n…[truncated]",
        );
    }

    private function field(string $label, ?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return "### {$label}\n".Str::limit($value, 4000);
    }

    private function listField(string $label, ?array $items): ?string
    {
        if (! $items || count($items) === 0) {
            return null;
        }

        $bullets = collect($items)->take(10)->map(fn ($i) => "- {$i}")->implode("\n");

        return "### {$label}\n{$bullets}";
    }
}
