<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
use App\Models\InternalNotification;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $openStageIds = DealStage::query()
            ->where('is_won', false)
            ->where('is_lost', false)
            ->pluck('id');

        $dealsByStage = DealStage::query()
            ->withCount('deals')
            ->orderBy('position')
            ->get(['id', 'name', 'slug', 'color'])
            ->map(fn (DealStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'slug' => $stage->slug,
                'color' => $stage->color,
                'deals_count' => $stage->deals_count,
            ]);

        $pipelineValue = Deal::query()
            ->whereIn('deal_stage_id', $openStageIds)
            ->sum('value');

        $upcomingDeals = Deal::query()
            ->with(['entity:id,name', 'person:id,name', 'stage:id,name,slug,color'])
            ->whereIn('deal_stage_id', $openStageIds)
            ->whereNotNull('expected_close_date')
            ->orderBy('expected_close_date')
            ->limit(5)
            ->get()
            ->map(fn (Deal $deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'value' => (float) $deal->value,
                'expected_close_date' => $deal->expected_close_date?->toDateString(),
                'entity' => $deal->entity?->only(['id', 'name']),
                'person' => $deal->person?->only(['id', 'name']),
                'stage' => $deal->relationLoaded('stage') && $deal->getRelation('stage')
                    ? $deal->getRelation('stage')->only(['id', 'name', 'slug', 'color'])
                    : null,
            ]);

        $todayEvents = CalendarEvent::query()
            ->whereDate('start_at', today())
            ->count();

        $pendingTasks = CalendarEvent::query()
            ->where('type', CalendarEvent::TYPE_TASK)
            ->where('status', CalendarEvent::STATUS_PENDING)
            ->count();

        $upcomingActivities = CalendarEvent::query()
            ->with(['owner:id,name', 'eventable'])
            ->where('status', CalendarEvent::STATUS_PENDING)
            ->where('start_at', '>=', now()->startOfDay())
            ->orderBy('start_at')
            ->limit(5)
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'start_at' => $event->start_at?->toDateTimeString(),
                'owner' => $event->owner?->only(['id', 'name']),
                'url' => route('calendar-events.show', $event),
            ]);

        $latestNotifications = InternalNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (InternalNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'type' => $notification->type,
                'read_at' => $notification->read_at?->toDateTimeString(),
                'created_at' => $notification->created_at?->toDateTimeString(),
            ]);

        $latestLeadSubmissions = LeadFormSubmission::query()
            ->with(['leadForm:id,name', 'createdDeal:id,title', 'createdPerson:id,name,email'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (LeadFormSubmission $submission) => [
                'id' => $submission->id,
                'name' => $submission->payload['name'] ?? null,
                'email' => $submission->payload['email'] ?? null,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'lead_form' => $submission->leadForm?->only(['id', 'name']),
                'created_deal' => $submission->createdDeal?->only(['id', 'title']),
                'created_person' => $submission->createdPerson?->only(['id', 'name', 'email']),
            ]);

        return Inertia::render('Dashboard', [
            'tenant' => $request->user()->currentTenant?->only(['id', 'name', 'slug']),
            'stats' => [
                'entities' => Entity::count(),
                'people' => Person::count(),
                'calendarEvents' => CalendarEvent::count(),
                'deals' => Deal::count(),
                'openDeals' => Deal::whereIn('deal_stage_id', $openStageIds)->count(),
                'pipelineValue' => (float) $pipelineValue,
                'todayEvents' => $todayEvents,
                'pendingTasks' => $pendingTasks,
                'activeAutomations' => AutomationRule::where('active', true)->whereNull('paused_at')->count(),
                'automationActivities' => AutomationRun::where('status', AutomationRun::STATUS_SUCCESS)->whereNotNull('calendar_event_id')->count(),
                'leadFormsActive' => LeadForm::where('active', true)->count(),
                'leadSubmissions' => LeadFormSubmission::count(),
            ],
            'dealsByStage' => $dealsByStage,
            'upcomingDeals' => $upcomingDeals,
            'upcomingActivities' => $upcomingActivities,
            'latestNotifications' => $latestNotifications,
            'latestLeadSubmissions' => $latestLeadSubmissions,
        ]);
    }
}
