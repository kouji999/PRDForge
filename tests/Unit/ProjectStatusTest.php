<?php

namespace Tests\Unit;

use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\ProjectLifecycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transitions(): void
    {
        $this->assertTrue(ProjectStatus::DISCOVERY->canTransitionTo(ProjectStatus::REQUIREMENTS_IN_PROGRESS));
        $this->assertTrue(ProjectStatus::REQUIREMENTS_IN_PROGRESS->canTransitionTo(ProjectStatus::READY_FOR_PRD));
        $this->assertTrue(ProjectStatus::READY_FOR_PRD->canTransitionTo(ProjectStatus::GENERATING));
        $this->assertTrue(ProjectStatus::REVIEW->canTransitionTo(ProjectStatus::APPROVED));
        $this->assertTrue(ProjectStatus::APPROVED->canTransitionTo(ProjectStatus::ARCHIVED));
    }

    public function test_invalid_transitions_rejected(): void
    {
        $this->assertFalse(ProjectStatus::DISCOVERY->canTransitionTo(ProjectStatus::APPROVED));
        $this->assertFalse(ProjectStatus::DISCOVERY->canTransitionTo(ProjectStatus::REVIEW));
        $this->assertFalse(ProjectStatus::ARCHIVED->canTransitionTo(ProjectStatus::DISCOVERY));
        $this->assertFalse(ProjectStatus::APPROVED->canTransitionTo(ProjectStatus::REQUIREMENTS_IN_PROGRESS));
        $this->assertFalse(ProjectStatus::GENERATING->canTransitionTo(ProjectStatus::DISCOVERY));
    }

    public function test_badge_map_contains_all_statuses(): void
    {
        $map = ProjectStatus::badgeMap();

        $this->assertCount(7, $map);
        $this->assertArrayHasKey('approved', $map);
        $this->assertEquals('Approved', $map['approved']['label']);
    }

    public function test_lifecycle_approve_flow_via_service(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create([
            'name' => 'Test',
            'slug' => 'test',
            'status' => ProjectStatus::REVIEW->value,
        ]);

        $lifecycle = app(ProjectLifecycle::class);
        $project = $lifecycle->approve($project);

        $this->assertEquals(ProjectStatus::APPROVED, $project->status);
        $this->assertNotNull($project->approved_at);
    }

    public function test_lifecycle_rejects_invalid_transition(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create([
            'name' => 'Test',
            'slug' => 'test',
            'status' => ProjectStatus::DISCOVERY->value,
        ]);

        $this->expectException(ValidationException::class);

        app(ProjectLifecycle::class)->approve($project);
    }
}
