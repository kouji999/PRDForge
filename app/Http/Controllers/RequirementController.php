<?php

namespace App\Http\Controllers;

use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $requirements = $project->requirements()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        return response()->json(['requirements' => $requirements]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'type' => ['required', 'in:functional,non_functional'],
            'title' => ['required', 'string', 'max:190'],
            'content' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $requirement = $project->requirements()->create([
            ...$data,
            'status' => 'confirmed',
            'source' => 'manual',
        ]);

        if ($project->status === ProjectStatus::DISCOVERY) {
            $project->forceFill(['status' => ProjectStatus::REQUIREMENTS_IN_PROGRESS])->save();
        }

        return response()->json(['requirement' => $requirement], 201);
    }

    public function update(Request $request, Project $project, Requirement $requirement): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);

        $data = $request->validate([
            'type' => ['sometimes', 'in:functional,non_functional'],
            'title' => ['sometimes', 'string', 'max:190'],
            'content' => ['sometimes', 'string', 'max:2000'],
            'priority' => ['sometimes', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:proposed,confirmed,rejected,needs_review'],
        ]);

        $requirement->update($data);

        return response()->json(['requirement' => $requirement->fresh()]);
    }

    public function destroy(Request $request, Project $project, Requirement $requirement): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);

        $requirement->delete();

        return response()->json(['ok' => true]);
    }
}
