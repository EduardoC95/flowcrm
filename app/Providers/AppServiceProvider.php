<?php

namespace App\Providers;

use App\Models\AIChatConversation;
use App\Models\AISuggestion;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealNote;
use App\Models\DealProposal;
use App\Models\Entity;
use App\Models\LeadForm;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AIChatPolicy;
use App\Policies\AISuggestionPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\DealPolicy;
use App\Policies\DealProposalPolicy;
use App\Policies\EntityPolicy;
use App\Policies\LeadFormPolicy;
use App\Policies\PersonPolicy;
use App\Observers\CalendarEventObserver;
use App\Observers\DealNoteObserver;
use App\Observers\DealObserver;
use App\Observers\PersonObserver;
use App\Services\Captcha\CaptchaVerifier;
use App\Services\Captcha\NullCaptchaVerifier;
use App\Services\Captcha\TurnstileCaptchaVerifier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CaptchaVerifier::class, function () {
            return match (config('captcha.driver')) {
                'turnstile' => new TurnstileCaptchaVerifier(),
                default => new NullCaptchaVerifier(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(AIChatConversation::class, AIChatPolicy::class);
        Gate::policy(AISuggestion::class, AISuggestionPolicy::class);
        Gate::policy(DealProposal::class, DealProposalPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(LeadForm::class, LeadFormPolicy::class);

        Gate::define('access-crm', fn (User $user): bool => $user->current_tenant_id !== null
            && $user->belongsToTenant($user->current_tenant_id));

        Gate::define('manage-tenant', fn (User $user, Tenant $tenant): bool => $user->roleForTenant($tenant->id) === Tenant::ROLE_OWNER);

        Deal::observe(DealObserver::class);
        CalendarEvent::observe(CalendarEventObserver::class);
        DealNote::observe(DealNoteObserver::class);
        Person::observe(PersonObserver::class);
    }
}
