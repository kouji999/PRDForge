<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get('/projects');

        $response->assertRedirect('/login');
    }

    public function test_owner_can_view_project(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Mine', 'slug' => 'mine']);

        $response = $this->actingAs($user)->get("/projects/{$project->id}");

        $response->assertOk();
    }

    public function test_other_user_cannot_view_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $owner->projects()->create(['name' => 'Secret', 'slug' => 'secret']);

        $response = $this->actingAs($intruder)->get("/projects/{$project->id}");

        $response->assertForbidden();
    }

    public function test_project_store_creates_context_and_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/projects', [
            'name' => 'New Project',
            'description' => 'Test description',
        ]);

        $project = Project::where('user_id', $user->id)->first();

        $response->assertRedirect("/projects/{$project->id}");
        $this->assertNotNull($project->context, 'Project context should be auto-created');
        $this->assertCount(1, $project->conversations, 'Discovery conversation should be auto-created');
    }

    public function test_other_user_cannot_generate_prd(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $owner->projects()->create(['name' => 'X', 'slug' => 'x']);

        $response = $this->actingAs($intruder)
            ->postJson("/projects/{$project->id}/prd/generate");

        $response->assertForbidden();
    }
}
