<?php

namespace App\Http\Controllers;

use App\Domain\Requirement\ReadinessEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReadinessEngine $readiness,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $projects = $user->projects()
            ->withCount('requirements')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $recentGenerations = $user->aiGenerations()
            ->latest()
            ->limit(8)
            ->get(['id', 'type', 'status', 'latency_ms', 'created_at']);

        $stats = [
            'active_projects' => $projects->whereNotIn('status', ['archived'])->count(),
            'total_requirements' => (int) $projects->sum('requirements_count'),
            'total_prds' => $user->projects()->whereHas('prd')->count(),
            'approved' => $projects->where('status', 'approved')->count(),
        ];

        $projectCards = $projects->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'status' => $p->status->value,
                'requirements_count' => $p->requirements_count,
                'has_prd' => $p->prd()->exists(),
                'updated_at' => $p->updated_at->toIso8601String(),
            ];
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'projects' => $projectCards,
            'recentGenerations' => $recentGenerations,
        ]);
    }
}
