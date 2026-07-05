<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\GymPolicy;
use App\Policies\MemberMembershipPolicy;
use App\Policies\MembershipPlanPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/tailadmin/components'));

        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Gym::class, GymPolicy::class);
        Gate::policy(MemberMembership::class, MemberMembershipPolicy::class);
        Gate::policy(MembershipPlan::class, MembershipPlanPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
