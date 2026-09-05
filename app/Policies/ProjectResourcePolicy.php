<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Prd;
use App\Models\PrdSection;
use App\Models\PrdVersion;
use App\Models\Project;
use App\Models\ProjectContext;
use App\Models\Requirement;
use App\Models\User;

/**
 * Generic ownership policy. Every domain model belongs to exactly one
 * project which belongs to exactly one user. Model name → project resolver.
 */
class ProjectResourcePolicy
{
    public function view(User $user, ProjectContext|Conversation|Requirement|Prd|PrdSection|PrdVersion|Message $resource): bool
    {
        return $this->resolveProject($resource)?->user_id === $user->id;
    }

    public function update(User $user, ProjectContext|Conversation|Requirement|Prd|PrdSection|PrdVersion|Message $resource): bool
    {
        return $this->resolveProject($resource)?->user_id === $user->id;
    }

    public function delete(User $user, ProjectContext|Conversation|Requirement|Prd|PrdSection|PrdVersion|Message $resource): bool
    {
        return $this->resolveProject($resource)?->user_id === $user->id;
    }

    private function resolveProject(mixed $resource): ?Project
    {
        return match (true) {
            $resource instanceof ProjectContext => $resource->project,
            $resource instanceof Conversation => $resource->project,
            $resource instanceof Message => $resource->conversation?->project,
            $resource instanceof Requirement => $resource->project,
            $resource instanceof Prd => $resource->project,
            $resource instanceof PrdSection => $resource->prd?->project,
            $resource instanceof PrdVersion => $resource->prd?->project,
            default => null,
        };
    }
}
