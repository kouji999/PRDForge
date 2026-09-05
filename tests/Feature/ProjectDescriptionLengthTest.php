<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDescriptionLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_description_accepted(): void
    {
        $user = User::factory()->create();

        // ~2k chars — realistic full product brief
        $desc = str_repeat('FORGE adalah platform AI yang mengubah ide mentah menjadi blueprint produk digital siap dikembangkan. ', 20);

        $response = $this->actingAs($user)->post('/projects', [
            'name' => 'Long Brief Project',
            'description' => $desc,
        ]);

        $project = \App\Models\Project::where('user_id', $user->id)->first();

        $response->assertRedirect();
        $this->assertNotNull($project);
        // TrimStrings middleware strips trailing whitespace — content intact
        $this->assertSame(rtrim($desc), $project->description);
        $this->assertGreaterThan(2000, strlen($project->description));
    }

    public function test_extreme_description_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/projects')->post('/projects', [
            'name' => 'Too Big',
            'description' => str_repeat('x', 100001),
        ]);

        $response->assertSessionHasErrors('description');
    }
}
