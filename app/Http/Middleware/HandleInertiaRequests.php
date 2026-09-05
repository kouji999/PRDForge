<?php

namespace App\Http\Middleware;

use App\Domain\Project\Enums\ProjectStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$count, $unread] = $this->sidebarBadges($request->user());

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'created_at' => $request->user()->created_at?->toIso8601String(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebar' => [
                'activeProjects' => $count,
                'unreadConversations' => $unread,
                'providerName' => fn () => $request->user()
                    ?->aiProviders()
                    ->where('is_default', true)
                    ->value('name'),
                'statuses' => ProjectStatus::badgeMap(),
            ],
        ];
    }

    private function sidebarBadges(?User $user): array
    {
        if (! $user) {
            return [0, 0];
        }

        $count = $user->projects()
            ->whereNotIn('status', [ProjectStatus::ARCHIVED->value])
            ->count();

        return [$count, 0];
    }
}
