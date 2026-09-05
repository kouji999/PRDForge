<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectContextController extends Controller
{
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'problem' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'target_users' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'product_concept' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'core_features' => ['sometimes', 'array', 'max:12'],
            'core_features.*' => ['string', 'max:200'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:500'],
            'constraints' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'goals' => ['sometimes', 'array', 'max:12'],
            'goals.*' => ['string', 'max:200'],
            'mvp_scope' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        $context = $project->ensureContext();
        $context->fill($data);
        $context->ai_extracted_at = null; // user edit overrides AI version
        $context->save();

        return response()->json(['ok' => true, 'context' => $context->fresh()]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(['context' => $project->ensureContext()]);
    }
}
