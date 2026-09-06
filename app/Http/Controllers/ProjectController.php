<?php

namespace App\Http\Controllers;

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\ProjectLifecycle;
use App\Domain\Requirement\ReadinessEngine;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $projects = $request->user()->projects()
            ->when(
                $status !== null && ProjectStatus::tryFrom($status) !== null,
                fn ($q) => $q->where('status', $status),
            )
            ->withCount('requirements')
            ->latest('updated_at')
            ->get()
            ->map(fn (Project $p) => $this->projectCard($p));

        $stats = $this->stats($request->user());

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'stats' => $stats,
            'filter' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:100000'],
        ]);

        $project = DB::transaction(function () use ($request, $data) {
            $project = $request->user()->projects()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($request->user()->id, $data['name']),
                'description' => $data['description'] ?? null,
                'status' => ProjectStatus::DISCOVERY,
            ]);

            $project->ensureContext();
            $project->conversations()->create(['title' => 'Discovery '.$data['name']]);

            return $project;
        });

        return redirect()->route('projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['context', 'requirements', 'prd.sections', 'conversations']);

        return Inertia::render('Projects/Show', [
            'project' => $this->projectDetail($project),
            'readiness' => app(ReadinessEngine::class)->evaluate($project)->toArray(),
            'conversation' => ($c = $project->activeConversation()) ? [
                'id' => $c->id,
                'title' => $c->title,
                'messages' => $c->latestMessages(50)->map(fn ($m) => $this->messageCard($m)),
            ] : null,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
        ]);

        $project->update($data);

        return back();
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project dihapus.');
    }

    public function transition(Request $request, Project $project, ProjectLifecycle $lifecycle): RedirectResponse
    {
        $this->authorize('update', $project);

        $action = $request->input('action');

        $project = match ($action) {
            'approve' => $lifecycle->approve($project),
            'archive' => $lifecycle->archive($project),
            'reopen' => $lifecycle->reopen($project),
            'to_requirements' => $lifecycle->transition($project, ProjectStatus::REQUIREMENTS_IN_PROGRESS),
            'to_ready' => $lifecycle->transition($project, ProjectStatus::READY_FOR_PRD),
            'to_review' => $lifecycle->transition($project, ProjectStatus::REVIEW),
            'back_to_ready' => $lifecycle->transition($project, ProjectStatus::READY_FOR_PRD),
            default => throw ValidationException::withMessages(['action' => 'Aksi tidak dikenal.']),
        };

        return back()->with('success', 'Status project diperbarui.');
    }

    private function uniqueSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'project';

        $slug = $base;
        $i = 1;

        while (Project::withTrashed()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function projectCard(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'status' => $p->status->value,
            'requirements_count' => $p->requirements_count,
            'has_prd' => $p->prd()->exists(),
            'updated_at' => $p->updated_at->toIso8601String(),
        ];
    }

    private function projectDetail(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'status' => $p->status->value,
            'ai_combo_id' => $p->ai_combo_id,
            'ai_combo_name' => $p->aiCombo?->name,
            'context' => $p->context ? [
                'problem' => $p->context->problem,
                'target_users' => $p->context->target_users,
                'product_concept' => $p->context->product_concept,
                'core_features' => $p->context->core_features ?? [],
                'platform' => $p->context->platform,
                'constraints' => $p->context->constraints,
                'goals' => $p->context->goals ?? [],
                'mvp_scope' => $p->context->mvp_scope,
            ] : null,
            'requirements' => $p->requirements->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'title' => $r->title,
                'content' => $r->content,
                'status' => $r->status,
                'source' => $r->source,
                'priority' => $r->priority,
            ]),
            'prd' => $p->prd ? [
                'id' => $p->prd->id,
                'title' => $p->prd->title,
                'summary' => $p->prd->summary,
                'status' => $p->prd->status,
                'sections_count' => $p->prd->sections->count(),
            ] : null,
            'conversations' => $p->conversations->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
            ]),
            'created_at' => $p->created_at->toIso8601String(),
            'updated_at' => $p->updated_at->toIso8601String(),
        ];
    }

    private function messageCard($m): array
    {
        return [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }

    private function stats($user): array
    {
        $projects = $user->projects()->withCount('requirements')->get();

        return [
            'active_projects' => $projects->whereNotIn('status', [ProjectStatus::ARCHIVED->value])->count(),
            'total_prds' => $user->projects()->whereHas('prd')->count(),
            'total_requirements' => (int) $projects->sum('requirements_count'),
            'approved' => $projects->where('status', ProjectStatus::APPROVED->value)->count(),
        ];
    }
}
