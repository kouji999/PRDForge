<?php

namespace App\Domain\Project;

use App\Domain\Prd\PrdEngine;
use App\Domain\Project\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

/**
 * Project lifecycle service. All status mutations go through here —
 * frontend never dictates status directly.
 */
class ProjectLifecycle
{
    private const STATUS_RULES = [
        'archive' => [ProjectStatus::DISCOVERY, ProjectStatus::REQUIREMENTS_IN_PROGRESS, ProjectStatus::READY_FOR_PRD, ProjectStatus::REVIEW, ProjectStatus::APPROVED],
        'unarchive' => [ProjectStatus::ARCHIVED],
    ];

    public function transition(Project $project, ProjectStatus $target): Project
    {
        if (! $project->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transisi {$project->status->value} → {$target->value} tidak valid.",
            ]);
        }

        $project->forceFill([
            'status' => $target,
            'approved_at' => $target === ProjectStatus::APPROVED ? now() : $project->approved_at,
        ])->save();

        return $project->fresh();
    }

    public function archive(Project $project): Project
    {
        return $this->transition($project, ProjectStatus::ARCHIVED);
    }

    public function approve(Project $project): Project
    {
        $project = $this->transition($project, ProjectStatus::APPROVED);

        if ($project->prd) {
            $project->prd->update(['status' => 'approved']);

            app(PrdEngine::class)
                ->createVersion($project->prd, 'Approved', 'v1.0');
        }

        return $project;
    }

    public function reopen(Project $project): Project
    {
        $target = $project->prd ? ProjectStatus::READY_FOR_PRD : ProjectStatus::REQUIREMENTS_IN_PROGRESS;

        return $this->transition($project, $target);
    }

    public function allowedTargets(Project $project): array
    {
        return array_keys(ProjectStatus::transitionTargets($project->status));
    }
}
