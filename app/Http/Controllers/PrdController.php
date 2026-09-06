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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrdController extends Controller
{
    public function __construct(
        private readonly PrdEngine $engine,
    ) {}

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->refresh();

        // Generation in flight: no complete PRD yet — send the user back to
        // the project chat view (it polls progress and redirects when done).
        if ($project->status === ProjectStatus::GENERATING) {
            return redirect()->route('projects.show', $project);
        }

        $prd = $project->prd()->with(['sections' => fn ($q) => $q->orderBy('order'), 'versions'])->first();

        if ($prd === null || $prd->sections()->count() === 0) {
            // Partial (crashed mid-generation) or none — back to chat view.
            return redirect()->route('projects.show', $project);
        }

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

        $report = app(ReadinessEngine::class)->evaluate($project);
        $force = $request->boolean('force');

        if (! $report->ready && ! $force) {
            return response()->json([
                'error' => 'Project belum ready ('.$report->score.'%). Info kurang: '.implode(', ', $report->missing).'.',
                'category' => 'not_ready',
                'score' => $report->score,
                'missing' => $report->missing,
            ], 422);
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
            'force' => $force,
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

    /** Export PRD as downloadable Markdown document. */
    public function export(Request $request, Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        $prd = $this->projectPrd($project);

        $filename = Str::slug($prd->title).'-'.now()->format('Ymd').'.md';

        return response()->streamDownload(function () use ($prd) {
            echo "# {$prd->title}\n\n";

            if ($prd->summary) {
                echo "> {$prd->summary}\n\n";
            }

            echo "---\n\n";

            foreach ($prd->sections()->orderBy('order')->get() as $section) {
                echo "## {$section->title}\n\n{$section->content}\n\n";
            }

            $latest = $prd->versions()->orderByDesc('id')->first();
            $versionLabel = $latest ? $latest->version : 'draft';
            $exportedAt = now()->format('d M Y H:i');

            echo "---\n\n";
            echo "_Diekspor dari PRDForge · versi terakhir: {$versionLabel} · {$exportedAt}_\n";
        }, $filename, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** Export PRD as styled PDF document. */
    public function exportPdf(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $prd = $this->projectPrd($project);

        $latest = $prd->versions()->orderByDesc('id')->first();
        $versionLabel = $latest ? $latest->version : 'draft';

        $sectionsHtml = '';
        foreach ($prd->sections()->orderBy('order')->get() as $section) {
            $sectionsHtml .= '<h2 class="sec-title">'.$section->title.'</h2>';
            $sectionsHtml .= '<div class="sec-body">'.$this->markdownToHtml($section->content).'</div>';
        }

        $pdf = \Pdf::loadView('prd.export-pdf', [
            'title' => $prd->title,
            'summary' => $prd->summary,
            'versionLabel' => $versionLabel,
            'sectionsHtml' => $sectionsHtml,
            'exportedAt' => now()->format('d F Y, H:i'),
        ]);
        $pdf->setPaper('a4');

        return $pdf->download(Str::slug($prd->title).'-'.now()->format('Ymd').'.pdf');
    }

    /** Minimal markdown → HTML for PDF (headings, bold, lists, code). */
    private function markdownToHtml(string $md): string
    {
        $esc = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

        $esc = preg_replace('/```(\w*)\r?\n(.*?)```/s', '<pre>$2</pre>', $esc);
        $esc = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $esc);
        $esc = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $esc);
        $esc = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $esc);
        $esc = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $esc);
        $esc = preg_replace('/`([^`]+)`/s', '<code>$1</code>', $esc);
        $esc = preg_replace('/^- (.+)$/m', '<li>$1</li>', $esc);
        $esc = str_replace('<li>', '<ul><li>', '');
        // group consecutive li
        $esc = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ul>$0</ul>', $esc);
        $esc = preg_replace('/<ul><ul>/s', '<ul>', $esc);
        $esc = preg_replace('/<\/ul><\/ul>/s', '</ul>', $esc);

        return $esc;
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
