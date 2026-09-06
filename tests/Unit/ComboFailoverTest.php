<?php

namespace Tests\Unit;

use App\Domain\Ai\Adapters\AiProviderException;
use App\Domain\Ai\AiService;
use App\Domain\Ai\Support\AiRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComboFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_combo_takes_priority_over_default_provider(): void
    {
        $user = User::factory()->create();
        $primary = $user->aiProviders()->create([
            'name' => 'Primary',
            'base_url' => 'http://primary.test/v1',
            'api_key' => 'key1',
            'model' => 'model-a',
            'status' => 'connected',
            'is_default' => true,
        ]);
        $backup = $user->aiProviders()->create([
            'name' => 'Backup',
            'base_url' => 'http://backup.test/v1',
            'api_key' => 'key2',
            'model' => 'model-b',
            'status' => 'connected',
        ]);

        $combo = $user->aiCombos()->create(['name' => 'Team', 'is_default' => false]);
        $combo->members()->create(['ai_provider_id' => $primary->id, 'priority' => 1]);
        $combo->members()->create(['ai_provider_id' => $backup->id, 'priority' => 2]);

        $project = $user->projects()->create([
            'name' => 'Combo Project',
            'slug' => 'combo-project',
            'ai_combo_id' => $combo->id,
        ]);

        // Primary fails (500), backup succeeds
        Http::fake([
            'primary.test/*' => Http::response(['error' => 'down'], 500),
            'backup.test/*' => Http::response([
                'choices' => [['message' => ['content' => '{"ok": true}']]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        $service = app(AiService::class);
        $result = $service->chatJson($user, new AiRequest(
            messages: [['role' => 'user', 'content' => 'test']],
            jsonMode: true,
        ), 'test', $project);

        $this->assertEquals(['ok' => true], $result);

        // Failover path logged
        $this->assertDatabaseHas('ai_usage_logs', [
            'provider_name' => 'Primary',
            'status' => 'error',
        ]);
        $this->assertDatabaseHas('ai_usage_logs', [
            'provider_name' => 'Backup',
            'status' => 'success',
        ]);
    }

    public function test_without_combo_uses_default_provider(): void
    {
        $user = User::factory()->create();
        $user->aiProviders()->create([
            'name' => 'Solo',
            'base_url' => 'http://solo.test/v1',
            'api_key' => 'key',
            'model' => 'model-x',
            'status' => 'connected',
            'is_default' => true,
        ]);

        $project = $user->projects()->create([
            'name' => 'Plain',
            'slug' => 'plain',
            // no combo
        ]);

        Http::fake([
            'solo.test/*' => Http::response([
                'choices' => [['message' => ['content' => '{"a": 1}']]],
            ]),
        ]);

        $result = app(AiService::class)->chatJson(
            $user,
            new AiRequest(messages: [['role' => 'user', 'content' => 'x']], jsonMode: true),
            'test',
            $project,
        );

        $this->assertEquals(['a' => 1], $result);
    }

    public function test_all_providers_failing_throws(): void
    {
        $user = User::factory()->create();
        $p1 = $user->aiProviders()->create(['name' => 'P1', 'base_url' => 'http://p1.test/v1',
            'api_key' => 'k', 'model' => 'm', 'status' => 'connected', 'is_default' => true,
        ]);
        $p2 = $user->aiProviders()->create(['name' => 'P2', 'base_url' => 'http://p2.test/v1',
            'api_key' => 'k', 'model' => 'm', 'status' => 'connected',
        ]);

        $combo = $user->aiCombos()->create(['name' => 'DeadTeam']);
        $combo->members()->create(['ai_provider_id' => $p1->id, 'priority' => 1]);
        $combo->members()->create(['ai_provider_id' => $p2->id, 'priority' => 2]);

        $project = $user->projects()->create([
            'name' => 'Doomed', 'slug' => 'doomed', 'ai_combo_id' => $combo->id,
        ]);

        Http::fake([
            '*' => Http::response(['error' => 'down'], 500),
        ]);

        $this->expectException(AiProviderException::class);

        app(AiService::class)->chatJson(
            $user,
            new AiRequest(messages: [['role' => 'user', 'content' => 'x']], jsonMode: true),
            'test',
            $project,
        );
    }
}
