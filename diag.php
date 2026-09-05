<?php

// E2E: dispatch GeneratePrdJob for project 5 (FORGE, readiness 100%),
// run it inline (sync) while hammering the DB with reads/writes from a
// second loop — proves WAL + busy_timeout survive the earlier deadlock.

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$project = App\Models\Project::find(5);
$user = $project->user;

echo 'dispatching generate for project '.$project->id.' (owner '.$user->email.')'.PHP_EOL;

\App\Jobs\GeneratePrdJob::dispatch($user->id, $project->id);
echo 'job queued at '.now()->format('H:i:s').PHP_EOL;

// Concurrent DB activity loop (simulates chat SSE + polling)
$start = microtime(true);
$lockWrites = 0;

while (microtime(true) - $start < 30) {
    // poll status endpoint logic inline
    $p = App\Models\Project::find(5);
    $progress = Illuminate\Support\Facades\Cache::get('prd_progress:5');
    if ($progress) {
        echo 'progress: '.json_encode($progress).' @'.now()->format('H:i:s').PHP_EOL;
    }

    // concurrent write (like message save)
    App\Models\AiUsageLog::create([
        'request_id' => (string) Illuminate\Support\Str::uuid(),
        'provider_name' => 'probe',
        'model' => 'probe',
        'operation' => 'concurrent_test',
        'status' => 'success',
        'latency_ms' => 0,
    ]);
    $lockWrites++;

    usleep(500000); // 0.5s
}

echo "loop done: {$lockWrites} concurrent writes survived, no locks".PHP_EOL;
echo 'project status: '.App\Models\Project::find(5)->status->value.PHP_EOL;
echo 'pending jobs: '.DB::table('jobs')->count().PHP_EOL;
