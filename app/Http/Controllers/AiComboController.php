<?php

namespace App\Http\Controllers;

use App\Models\AiCombo;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiComboController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $combos = $request->user()->aiCombos()
            ->with(['members.provider' => fn ($q) => $q->select('id', 'name', 'model', 'status')])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return response()->json(['combos' => $combos->map(fn ($c) => $this->payload($c))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'provider_ids' => ['required', 'array', 'min:1', 'max:5'],
            'provider_ids.*' => ['integer', 'distinct'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        // All providers must belong to the user
        $owned = $request->user()->aiProviders()->whereIn('id', $data['provider_ids'])->pluck('id');
        abort_unless($owned->count() === count(array_unique($data['provider_ids'])), 403, 'Provider tidak valid.');

        $combo = DB::transaction(function () use ($request, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->aiCombos()->update(['is_default' => false]);
            }

            $combo = $request->user()->aiCombos()->create([
                'name' => $data['name'],
                'is_default' => $data['is_default'] ?? false,
            ]);

            foreach (array_values($data['provider_ids']) as $index => $providerId) {
                $combo->members()->create([
                    'ai_provider_id' => $providerId,
                    'priority' => $index + 1,
                ]);
            }

            return $combo;
        });

        return response()->json(['combo' => $this->payload($combo->fresh(['members.provider']))], 201);
    }

    public function update(Request $request, AiCombo $combo): JsonResponse
    {
        abort_unless($combo->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'provider_ids' => ['sometimes', 'array', 'min:1', 'max:5'],
            'provider_ids.*' => ['integer', 'distinct'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $combo, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->aiCombos()->whereKeyNot($combo->id)->update(['is_default' => false]);
            }

            $combo->update(collect($data)->only(['name', 'is_default'])->all());

            if (isset($data['provider_ids'])) {
                $owned = $request->user()->aiProviders()->whereIn('id', $data['provider_ids'])->pluck('id');
                abort_unless($owned->count() === count(array_unique($data['provider_ids'])), 403, 'Provider tidak valid.');

                $combo->members()->delete();

                foreach (array_values($data['provider_ids']) as $index => $providerId) {
                    $combo->members()->create([
                        'ai_provider_id' => $providerId,
                        'priority' => $index + 1,
                    ]);
                }
            }
        });

        return response()->json(['combo' => $this->payload($combo->fresh(['members.provider']))]);
    }

    public function destroy(Request $request, AiCombo $combo): JsonResponse
    {
        abort_unless($combo->user_id === $request->user()->id, 404);

        $wasDefault = $combo->is_default;
        $combo->delete();

        if ($wasDefault) {
            $request->user()->aiCombos()->first()?->update(['is_default' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /** Attach a combo to a project (its AI team). */
    public function assignToProject(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'combo_id' => ['nullable', 'integer'],
        ]);

        if ($data['combo_id'] === null) {
            $project->update(['ai_combo_id' => null]);

            return response()->json(['ok' => true, 'combo_id' => null]);
        }

        $combo = $request->user()->aiCombos()->find($data['combo_id']);
        abort_unless($combo !== null, 404, 'Combo tidak ditemukan.');

        $project->update(['ai_combo_id' => $combo->id]);

        return response()->json(['ok' => true, 'combo_id' => $combo->id]);
    }

    private function payload(AiCombo $combo): array
    {
        return [
            'id' => $combo->id,
            'name' => $combo->name,
            'is_default' => $combo->is_default,
            'providers' => $combo->members->map(fn ($m) => [
                'id' => $m->provider?->id,
                'name' => $m->provider?->name,
                'model' => $m->provider?->model,
                'status' => $m->provider?->status,
                'priority' => $m->priority,
            ])->values(),
        ];
    }
}
