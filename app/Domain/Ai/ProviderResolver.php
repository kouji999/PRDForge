<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Adapters\OpenAiCompatibleAdapter;
use App\Domain\Ai\Contracts\AiProviderContract;
use App\Models\AiProvider;
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
