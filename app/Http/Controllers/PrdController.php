<?php

namespace App\Http\Controllers;

use App\Domain\Prd\PrdEngine;
use App\Domain\Prd\SectionRegistry;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Requirement\ReadinessEngine;
use App\Jobs\GeneratePrdJob;
use App\Models\Prd;
use App\Models\PrdSection;
use App\Models\Project;
use App\Services\PrdGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PrdController extends Controller
{
    public function __construct(
        private readonly PrdEngine $engine,
    ) {}

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $prd = $project->prd()->with(['sections' => fn ($q) => $q->orderBy('order'), 'versions'])->first();

        abort_unless($prd !== null, 404, 'PRD belum dibuat.');

        return Inertia::render('Prd/Workspace', [
            'project' => $this->projectSummary($project),
            'prd' => $this->prdPayload($prd),
            'versions' => $prd->versions->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'label' => $v->label,
                'section_count' => $v->section_count,
                'created_at' => $v->created_at->toIso8601String(),
            ]),
            'registry' => SectionRegistry::SECTIONS,
        ]);
    }

    /** Dispatch background PRD generation. Returns immediately. */
    public function generate(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        if (! $this->readinessReady($project)) {
            return response()->json(['error' => 'Project belum ready. Lengkapi requirement dulu.', 'category' => 'not_ready'], 422);
        }

        // One generation at a time per project.
        $running = Cache::get(GeneratePrdJob::progressKey($project->id));

        if ($running && $running['error'] === null && $running['total'] > 0 && $project->status === ProjectStatus::GENERATING) {
            return response()->json(['status' => 'already_generating', 'progress' => $running]);
        }

        $project->forceFill(['status' => ProjectStatus::GENERATING->value])->save();

        GeneratePrdJob::dispatch($request->user()->id, $project->id);

        return response()->json([
            'status' => 'generating',
            'progress' => ['chunk' => 0, 'total' => count(PrdGenerationService::SECTION_CHUNKS)],
        ]);
    }

    /** Poll generation status. */
    public function status(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $progress = Cache::get(GeneratePrdJob::progressKey($project->id));

        $project->refresh();

        return response()->json([
            'status' => $project->status->value,
            'progress' => $progress,
            'prd_ready' => $project->prd()->exists() && $project->status !== ProjectStatus::GENERATING,
        ]);
    }

    private function readinessReady(Project $project): bool
    {
        return app(ReadinessEngine::class)->evaluate($project)->ready;
    }

    /** Resolve the project's PRD or 404. Route has no {prd} segment — implicit binding can't do it. */
    private function projectPrd(Project $project): Prd
    {
        $prd = $project->prd()->first();
        abort_unless($prd !== null, 404, 'PRD belum dibuat.');

        return $prd;
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:800'],
        ]);

        $prd->update($data);

        return response()->json(['prd' => $this->prdPayload($prd->fresh(['sections']))]);
    }

    public function updateSection(Request $request, Project $project, PrdSection $section): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);
        abort_unless($section->prd_id === $prd->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'content' => ['sometimes', 'string', 'max:20000'],
            'status' => ['sometimes', 'in:draft,reviewed,approved'],
        ]);

        $section->update($data);

        return response()->json(['section' => $section->fresh()]);
    }

    public function reorderSections(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);

        $order = $request->validate([
            'order' => ['required', 'array', 'max:30'],
            'order.*' => ['integer'],
        ])['order'];

        DB::transaction(function () use ($prd, $order) {
            foreach (array_values($order) as $index => $sectionId) {
                $prd->sections()->whereKey($sectionId)->update(['order' => $index]);
            }
        });

        return response()->json(['prd' => $this->prdPayload($prd->fresh(['sections']))]);
    }

    public function destroySection(Request $request, Project $project, PrdSection $section): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);
        abort_unless($section->prd_id === $prd->id, 404);

        $section->delete();

        return response()->json(['ok' => true]);
    }

    /** AI action on a section → proposal only (safety flow). */
    public function sectionAction(Request $request, Project $project, PrdSection $section): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);
        abort_unless($section->prd_id === $prd->id, 404);

        $action = $request->validate([
            'action' => ['required', 'in:rewrite,expand,simplify,review,contradictions'],
        ])['action'];

        try {
            $proposal = $this->engine->sectionAction($request->user(), $prd, $section, $action);

            return response()->json(['proposal' => $proposal->toArray()]);
        } catch (ProviderNotConfiguredException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => 'no_provider'], 422);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => $e->category], 502);
        } catch (\Throwable) {
            return response()->json(['error' => 'AI action gagal. Coba lagi.', 'category' => 'internal'], 500);
        }
    }

    /** Apply a confirmed proposal. */
    public function applySectionProposal(Request $request, Project $project, PrdSection $section): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);
        abort_unless($section->prd_id === $prd->id, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
        ]);

        $section = $this->engine->applyProposal($request->user(), $section, $data['content']);

        return response()->json(['section' => $section]);
    }

    /** Full PRD AI review. */
    public function review(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $prd = $this->projectPrd($project);

        try {
            $result = $this->engine->review($request->user(), $prd);

            return response()->json(['review' => $result->toArray()]);
        } catch (ProviderNotConfiguredException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => 'no_provider'], 422);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage(), 'category' => $e->category], 502);
        } catch (\Throwable) {
            return response()->json(['error' => 'Review gagal. Coba lagi.', 'category' => 'internal'], 500);
        }
    }

    /** Create immutable version snapshot. */
    public function storeVersion(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $prd = $this->projectPrd($project);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $version = $this->engine->createVersion($prd, $data['label'] ?? null);

        return response()->json([
            'version' => [
                'id' => $version->id,
                'version' => $version->version,
                'label' => $version->label,
                'section_count' => $version->section_count,
                'created_at' => $version->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function showVersion(Request $request, Project $project, $versionId): JsonResponse
    {
        $this->authorize('view', $project);
        $prd = $this->projectPrd($project);

        $version = $prd->versions()->findOrFail($versionId);

        return response()->json(['version' => $version]);
    }

    private function prdPayload(Prd $prd): array
    {
        return [
            'id' => $prd->id,
            'title' => $prd->title,
            'summary' => $prd->summary,
            'status' => $prd->status,
            'sections' => $prd->sections->map(fn (PrdSection $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'title' => $s->title,
                'content' => $s->content,
                'order' => $s->order,
                'status' => $s->status,
                'updated_at' => $s->updated_at->toIso8601String(),
            ]),
        ];
    }

    private function projectSummary(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'status' => $p->status->value,
        ];
    }
}
