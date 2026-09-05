<?php

namespace App\Http\Controllers;

use App\Domain\Ai\Contracts\AiProviderContract;
use App\Domain\Ai\ProviderResolver;
use App\Models\AiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providers = $request->user()->aiProviders()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'name', 'base_url', 'model', 'status', 'last_error', 'is_default', 'last_tested_at', 'created_at', 'updated_at']);

        return response()->json(['providers' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['required', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $provider = DB::transaction(function () use ($request, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->aiProviders()->update(['is_default' => false]);
            }

            $hasDefault = $request->user()->aiProviders()->where('is_default', true)->exists();

            return $request->user()->aiProviders()->create([
                ...$data,
                'is_default' => ($data['is_default'] ?? false) || ! $hasDefault,
                'status' => 'untested',
            ]);
        });

        return response()->json(['provider' => $this->safePayload($provider)], 201);
    }

    public function update(Request $request, AiProvider $provider): JsonResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'base_url' => ['sometimes', 'url', 'max:500'],
            'api_key' => ['sometimes', 'string', 'max:500'],
            'model' => ['sometimes', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $provider, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->aiProviders()->whereKeyNot($provider->id)->update(['is_default' => false]);
            }

            $provider->update($data);
        });

        return response()->json(['provider' => $this->safePayload($provider->fresh())]);
    }

    public function destroy(Request $request, AiProvider $provider): JsonResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $wasDefault = $provider->is_default;
        $provider->delete();

        if ($wasDefault) {
            $next = $request->user()->aiProviders()->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /** Test connection flow: validate → call → normalize → Connected/Failed. */
    public function test(Request $request, ?AiProvider $provider = null): JsonResponse
    {
        $resolver = app(ProviderResolver::class);

        if ($provider === null || ! $provider->exists) {
            // Test un-saved config from form
            $data = $request->validate([
                'base_url' => ['required', 'url', 'max:500'],
                'api_key' => ['required', 'string', 'max:500'],
                'model' => ['required', 'string', 'max:120'],
            ]);

            $adapter = $resolver->resolveFor(new AiProvider([
                'base_url' => $data['base_url'],
                'api_key' => $data['api_key'],
                'model' => $data['model'],
                'name' => 'test',
            ]));
        } else {
            abort_unless($provider->user_id === $request->user()->id, 404);

            $adapter = $resolver->resolveFor($provider);
        }

        $result = $adapter->testConnection();

        if ($provider !== null && $provider->exists) {
            $provider->update([
                'status' => $result->connected ? 'connected' : 'error',
                'last_error' => $result->connected ? null : $result->message,
                'last_tested_at' => now(),
            ]);
        }

        return response()->json([
            'connected' => $result->connected,
            'message' => $result->message,
            'category' => $result->errorCategory,
            'latency_ms' => $result->latencyMs,
        ]);
    }

    public function models(Request $request, AiProvider $provider): JsonResponse
    {
        abort_unless($provider->user_id === $request->user()->id, 404);

        $resolver = app(ProviderResolver::class);

        /** @var AiProviderContract $adapter */
        $adapter = $resolver->resolveFor($provider);

        return response()->json(['models' => $adapter->listModels()]);
    }

    private function safePayload(AiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'base_url' => $provider->base_url,
            'model' => $provider->model,
            'status' => $provider->status,
            'last_error' => $provider->last_error,
            'is_default' => $provider->is_default,
            'last_tested_at' => $provider->last_tested_at?->toIso8601String(),
        ];
    }
}
