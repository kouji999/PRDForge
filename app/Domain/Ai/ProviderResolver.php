<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Adapters\OpenAiCompatibleAdapter;
use App\Domain\Ai\Contracts\AiProviderContract;
use App\Models\AiCombo;
use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;

/**
 * Resolves the active provider for a user into a provider-agnostic adapter.
 * Core engine never references 9Router or any concrete vendor.
 */
class ProviderResolver
{
    public function resolve(User $user): AiProviderContract
    {
        $provider = $user->defaultProvider();

        if (! $provider) {
            $provider = $this->resolveSystemDefault($user);
        }

        if (! $provider) {
            throw ProviderNotConfiguredException::because('Belum ada AI provider yang dikonfigurasi.');
        }

        return new OpenAiCompatibleAdapter(
            baseUrl: $provider->base_url,
            apiKey: $provider->api_key,
            model: $provider->model,
            providerId: $provider->id,
            providerName: $provider->name,
        );
    }

    public function resolveFor(AiProvider $provider): AiProviderContract
    {
        return new OpenAiCompatibleAdapter(
            baseUrl: $provider->base_url,
            apiKey: $provider->api_key,
            model: $provider->model,
            providerId: $provider->id,
            providerName: $provider->name,
        );
    }

    /** Resolves the ordered adapters for a project: combo first, then user default, then system seed. */
    public function resolveChain(User $user, ?Project $project = null): array
    {
        $adapters = [];

        $comboId = $project?->ai_combo_id;

        if ($comboId) {
            $combo = AiCombo::with(['members.provider'])->find($comboId);

            if ($combo && $combo->user_id === $user->id) {
                foreach ($combo->members as $member) {
                    if ($member->provider && $member->provider->deleted_at === null) {
                        $adapters[] = $this->resolveFor($member->provider);
                    }
                }
            }
        }

        if ($adapters === []) {
            $adapters[] = $this->resolve($user);
        }

        return $adapters;
    }

    /** Seed user's first provider from system env if none configured. */
    private function resolveSystemDefault(User $user): ?AiProvider
    {
        $url = config('services.ai.default_base_url');
        $key = config('services.ai.default_api_key');
        $model = config('services.ai.default_model');

        if (! $url || ! $key || ! $model) {
            return null;
        }

        return $user->aiProviders()->create([
            'name' => '9Router',
            'base_url' => $url,
            'api_key' => $key,
            'model' => $model,
            'status' => 'untested',
            'is_default' => true,
        ]);
    }
}
