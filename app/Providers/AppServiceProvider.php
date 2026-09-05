<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Prd;
use App\Models\PrdSection;
use App\Models\PrdVersion;
use App\Models\Project;
use App\Models\ProjectContext;
use App\Models\Requirement;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectResourcePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectContext::class, ProjectResourcePolicy::class);
        Gate::policy(Conversation::class, ProjectResourcePolicy::class);
        Gate::policy(Message::class, ProjectResourcePolicy::class);
        Gate::policy(Requirement::class, ProjectResourcePolicy::class);
        Gate::policy(Prd::class, ProjectResourcePolicy::class);
        Gate::policy(PrdSection::class, ProjectResourcePolicy::class);
        Gate::policy(PrdVersion::class, ProjectResourcePolicy::class);
    }
}
