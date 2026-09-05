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

    public int $tries = 1;

    public int $retryUntil = 3700;

    /** Don't release duplicate back into queue. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly int $userId,
        public readonly int $projectId,
    ) {}

    public function handle(PrdGenerationService $service): void
    {
        set_time_limit(0);

        $user = User::findOrFail($this->userId);
        $project = Project::findOrFail($this->projectId);

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
            $project->forceFill(['status' => ProjectStatus::READY_FOR_PRD->value])->save();

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
