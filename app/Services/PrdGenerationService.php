<?php

namespace App\Services;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use App\Domain\Ai\ContextBuilder;
use App\Domain\Ai\Prompts\PromptLibrary;
use App\Domain\Ai\Support\AiRequest;
use App\Domain\Ai\Support\ErrorNormalizer;
use App\Domain\Prd\SectionRegistry;
use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Prd;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chunked PRD generation with per-chunk retries.
 * Used by GeneratePrdJob (queue) — never inside HTTP request.
 */
class PrdGenerationService
{
    /** Chunks of canonical section keys — sized for reasoning-model budgets. */
    public const SECTION_CHUNKS = [
        ['overview', 'problem', 'goals', 'target_users', 'user_personas'],
        ['product_scope', 'mvp_scope', 'user_journey'],
        ['features', 'functional_requirements'],
        ['non_functional_requirements', 'ux_requirements'],
        ['technical_requirements', 'data_requirements'],
        ['api_requirements', 'security_requirements', 'analytics'],
        ['risks', 'dependencies', 'success_metrics', 'roadmap'],
    ];

    private const CHUNK_ATTEMPTS = 3;

    public function __construct(
        private readonly AiService $ai,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  callable(int, int): void  $onProgress  receives (chunkNumber, totalChunks)
     * @param  bool  $fresh  true = wipe existing PRD; false = resume missing chunks only.
     */
    public function generateChunked(User $user, Project $project, ?callable $onProgress = null, bool $fresh = true): Prd
    {
        set_time_limit(0);
        ignore_user_abort(false);

        $context = $this->contextBuilder->contextBlock($project);
        $total = count(self::SECTION_CHUNKS);

        $prd = $this->preparePrd($project, $fresh);

        $existingKeys = $fresh ? [] : $prd->sections()->pluck('key')->all();
        $order = (int) $prd->sections()->max('order') + 1;

        foreach (self::SECTION_CHUNKS as $index => $chunk) {
            // Resume mode: skip chunks whose sections already exist
            if (! $fresh && count(array_intersect($chunk, $existingKeys)) >= max(1, count($chunk) - 1)) {
                continue;
            }

            if ($onProgress) {
                $onProgress($index + 1, $total);
            }

            $withMeta = $index === 0 && ! $prd->title;
            $data = $this->generateChunkWithRetry($user, $project, $context, $chunk, $withMeta, $prd);

            if ($withMeta) {
                $prd->update([
                    'title' => mb_substr(trim((string) ($data['title'] ?? $project->name)), 0, 180),
                    'summary' => mb_substr(trim((string) ($data['summary'] ?? '')), 0, 800),
                ]);
            }

            $this->persistChunkSections($prd, $data['sections'] ?? [], $order);
        }

        $this->ensureCanonicalSections($prd);

        $project->ensureContext();
        $project->forceFill(['status' => ProjectStatus::REVIEW->value])->save();

        return $prd->fresh(['sections']);
    }

    /** New or reset Prd shell before generation begins. */
    private function preparePrd(Project $project, bool $fresh): Prd
    {
        $prd = $project->prd()->withTrashed()->first();

        if ($prd) {
            $prd->restore();

            if ($fresh) {
                $prd->sections()->delete();
            }
        } else {
            $prd = $project->prd()->create([
                'title' => $project->name,
                'status' => 'draft',
            ]);
        }

        if ($fresh) {
            $prd->update(['title' => $project->name, 'summary' => null, 'status' => 'draft']);
        }

        return $prd;
    }

    /** Generate one chunk; retries provider errors up to CHUNK_ATTEMPTS. */
    private function generateChunkWithRetry(User $user, Project $project, string $context, array $chunk, bool $withMeta, Prd $prd): array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::CHUNK_ATTEMPTS; $attempt++) {
            try {
                return $this->generateChunk($user, $project, $context, $chunk, $withMeta, $prd);
            } catch (AiProviderException $e) {
                $lastError = $e;

                // Do not retry deterministic client errors.
                if (in_array($e->category, [ErrorNormalizer::INVALID_KEY, ErrorNormalizer::INVALID_URL, ErrorNormalizer::INVALID_MODEL], true)) {
                    throw $e;
                }

                Log::warning('prd.chunk.retry', [
                    'project' => $project->id,
                    'chunk' => $chunk[0] ?? '?',
                    'attempt' => $attempt,
                    'category' => $e->category,
                ]);

                if ($attempt < self::CHUNK_ATTEMPTS) {
                    sleep(5 * $attempt); // backoff
                }
            }
        }

        throw $lastError ?? new AiProviderException('Generation gagal.', 'provider_error');
    }

    /** One chunked generation call. */
    private function generateChunk(User $user, Project $project, string $context, array $chunk, bool $withMeta, Prd $existing): array
    {
        $chunkList = implode(', ', $chunk);

        // Chunk 1 sets title/summary; later chunks get only a compact
        // section-index (title + one-line gist) — sending full prior PRD
        // makes reasoning models burn their token budget thinking.
        if ($withMeta) {
            $extra = 'Tulis juga field "title" (nama produk/PRD) dan "summary" (2-3 kalimat).';
        } else {
            $index = $existing->sections()
                ->orderBy('order')
                ->get()
                ->map(fn ($s) => '- '.$s->title.' ('.$s->key.')')
                ->implode("\n");
            $extra = "Section yang SUDAH dibuat (jangan ulangi, cukup konsisten):\n{$index}";
        }

        $request = new AiRequest(
            messages: [[
                'role' => 'user',
                'content' => "Buat PRD untuk project \"{$project->name}\".\n\nKonteks:\n{$context}\n\n{$extra}\n\nUntuk request ini, TULIS HANYA section berikut: {$chunkList}. Format setiap section markdown ringkas (heading, bullet, tabel bila relevan). JANGAN berpikir panjang — langsung tulis JSON.",
            ]],
            systemPrompt: PromptLibrary::prdGenerationSystem(),
            temperature: 0.4,
            maxTokens: 16000,
            jsonMode: true,
            timeoutSeconds: 600,
        );

        $data = $this->ai->chatJson($user, $request, 'prd_generation');

        if (! isset($data['sections']) || ! is_array($data['sections'])) {
            throw new AiProviderException('Chunk tanpa sections.', ErrorNormalizer::INVALID_OUTPUT);
        }

        return $data;
    }

    /** Persist chunk sections with canonical key validation. */
    private function persistChunkSections(Prd $prd, array $rawSections, int &$order): void
    {
        foreach ($rawSections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = (string) ($section['key'] ?? '');
            $content = (string) ($section['content'] ?? '');

            if ($key === '' || trim($content) === '' || ! SectionRegistry::isKnown($key)) {
                continue;
            }

            if ($prd->sections()->where('key', $key)->exists()) {
                continue;
            }

            $prd->sections()->create([
                'key' => $key,
                'title' => mb_substr((string) ($section['title'] ?? SectionRegistry::title($key)), 0, 190),
                'content' => $content,
                'order' => $order++,
                'status' => 'draft',
            ]);
        }
    }

    /** Fill any missing canonical section with placeholder; reorder canonically. */
    private function ensureCanonicalSections(Prd $prd): void
    {
        $order = (int) $prd->sections()->max('order') + 1;

        foreach (SectionRegistry::keys() as $key) {
            if (! $prd->sections()->where('key', $key)->exists()) {
                $prd->sections()->create([
                    'key' => $key,
                    'title' => SectionRegistry::title($key),
                    'content' => '_Belum ada konten. Gunakan AI actions untuk mengisi section ini._',
                    'order' => $order++,
                    'status' => 'draft',
                ]);
            }
        }

        $i = 0;
        foreach (SectionRegistry::keys() as $key) {
            DB::table('prd_sections')
                ->where('prd_id', $prd->id)
                ->where('key', $key)
                ->update(['order' => $i++]);
        }
    }
}
