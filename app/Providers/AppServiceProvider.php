<?php

namespace App\Providers;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CalendarEventPolicy;
use App\Policies\DealPolicy;
use App\Policies\EntityPolicy;
use App\Policies\PersonPolicy;
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
        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);

        Gate::define('access-crm', fn (User $user): bool => $user->current_tenant_id !== null
            && $user->belongsToTenant($user->current_tenant_id));

        Gate::define('manage-tenant', fn (User $user, Tenant $tenant): bool => $user->roleForTenant($tenant->id) === Tenant::ROLE_OWNER);
    }
}
