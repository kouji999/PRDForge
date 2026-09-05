<?php

namespace Tests\Unit;

use App\Domain\Requirement\ReadinessEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_project_not_ready(): void
    {
        $project = User::factory()->create()->projects()->create([
            'name' => 'Empty',
            'slug' => 'empty',
        ]);

        $report = app(ReadinessEngine::class)->evaluate($project);

        $this->assertFalse($report->ready);
        $this->assertEquals(0, $report->score);
        $this->assertCount(6, $report->criteria);
    }

    public function test_fully_specified_project_ready(): void
    {
        $project = User::factory()->create()->projects()->create([
            'name' => 'Full',
            'slug' => 'full',
        ]);

        $project->ensureContext()->update([
            'problem' => 'Lifter intermediate sering plateau karena tidak sadar fatigue accumulation dan volume stagnan selama berminggu-minggu.',
            'target_users' => 'Lifter dengan pengalaman gym 2 sampai 5 tahun yang fokus pada progressive overload.',
            'product_concept' => 'Aplikasi gym tracker mobile dengan deteksi plateau otomatis dan dashboard progress mingguan.',
            'core_features' => ['1-tap logging', 'Plateau detection', 'Weekly dashboard'],
            'mvp_scope' => 'MVP mencakup logging, dashboard, dan deteksi plateau rule-based dengan minimal 3 minggu data.',
        ]);

        $report = app(ReadinessEngine::class)->evaluate($project);

        $this->assertTrue($report->ready, 'Criteria: '.json_encode($report->criteria));
        $this->assertEquals(100, $report->score);
        $this->assertEmpty($report->missing);
    }

    public function test_journey_implied_by_confirmed_requirement(): void
    {
        $project = User::factory()->create()->projects()->create([
            'name' => 'Journey',
            'slug' => 'journey',
        ]);

        $project->ensureContext()->update([
            'problem' => 'Lifter intermediate sering plateau karena tidak sadar fatigue accumulation dan volume stagnan.',
            'target_users' => 'Lifter dengan pengalaman gym 2 sampai 5 tahun yang fokus progressive overload.',
            'product_concept' => 'Aplikasi gym tracker mobile dengan deteksi plateau otomatis dan dashboard progress.',
            'core_features' => ['1-tap logging', 'Plateau detection'],
            // mvp_scope empty — journey criterion should be met by confirmed requirement
        ]);

        $project->requirements()->create([
            'type' => 'functional',
            'title' => 'REQ-1',
            'content' => 'User dapat log workout',
            'status' => 'confirmed',
            'source' => 'manual',
        ]);

        $report = app(ReadinessEngine::class)->evaluate($project);

        $journey = collect($report->criteria)->firstWhere('key', 'user_journey');
        $this->assertEquals('met', $journey['status']);
    }
}
