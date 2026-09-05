<?php

namespace App\Jobs;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\Support\ErrorNormalizer;
use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Prd;
use App\Models\Project;
use App\Models\User;
use App\Services\PrdGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Chunked PRD generation as background job.
 * - Progress persisted to cache per chunk → survives job retries.
 * - Per-chunk AI retries (3x) tolerate flaky free-tier providers.
 * - Frontend polls generateStatus endpoint.
 */
class GeneratePrdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /**
     * Attempts allowed: worker restarts mid-run re-reserve the job;
     * handle() is idempotent (resume mode skips completed chunks).
     */
    public int $tries = 3;

    /**
     * Job may legitimately run >1h (4 reasoning-model chunks) — allow
     * re-dispatch window well past that before queue counts it as lost.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds(7200);
    }

    /** Don't release duplicate back into queue. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly int $userId,
        public readonly int $projectId,
    ) {}

    public function handle(PrdGenerationService $service): void
    {
        set_time_limit(0);

        // SQLite write-lock contention with concurrent requests (chat SSE,
        // polling) — retry instead of failing the whole generation.
        $attempts = 0;

        do {
            $attempts++;

            try {
                $this->runGeneration($service);

                return;
            } catch (QueryException $e) {
                $locked = str_contains(strtolower($e->getMessage()), 'database is locked')
                    || str_contains(strtolower($e->getMessage()), 'busy');

                if (! $locked || $attempts >= 5) {
                    throw $e;
                }

                sleep(3 * $attempts);
            }
        } while (true);
    }

    private function runGeneration(PrdGenerationService $service): void
    {
        $user = User::findOrFail($this->userId);
        $project = Project::findOrFail($this->projectId);

        // Re-assert generating status: retries/releases may have reset it
        // while the job is legitimately still producing sections.
        $project->forceFill(['status' => ProjectStatus::GENERATING->value])->save();

        // Resume if a previous attempt already persisted sections (retry-after release).
        $hasExisting = $project->prd()->withTrashed()->whereHas('sections')->exists();

        $service->generateChunked(
            user: $user,
            project: $project,
            onProgress: function (int $chunk, int $total) use ($project) {
                Cache::put(self::progressKey($project->id), [
                    'chunk' => $chunk,
                    'total' => $total,
                    'error' => null,
                ], now()->addHours(3));
            },
            fresh: ! $hasExisting,
        );
    }

    public function failed(\Throwable $e): void
    {
        $project = Project::find($this->projectId);

        if ($project) {
            // DB may be busy when failure fires — retry the status write.
            $attempts = 0;

            while ($attempts < 5) {
                $attempts++;

                try {
                    $project->forceFill(['status' => ProjectStatus::READY_FOR_PRD->value])->save();
                    break;
                } catch (QueryException) {
                    if ($attempts >= 5) {
                        break;
                    }
                    sleep(2);
                }
            }

            $category = $e instanceof AiProviderException ? $e->category : 'unknown';

            Cache::put(self::progressKey($project->id), [
                'chunk' => 0,
                'total' => 0,
                'error' => ErrorNormalizer::message($category),
            ], now()->addHours(1));
        }

        Log::error('prd.job.failed', [
            'project' => $this->projectId,
            'error' => ErrorNormalizer::sanitize($e->getMessage()),
        ]);
    }

    public static function progressKey(int $projectId): string
    {
        return "prd_progress:{$projectId}";
    }
}
