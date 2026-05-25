<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Entity;
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

        return Inertia::render('Dashboard', [
            'tenant' => $request->user()->currentTenant?->only(['id', 'name', 'slug']),
            'stats' => [
                'entities' => Entity::count(),
                'people' => Person::count(),
                'calendarEvents' => CalendarEvent::count(),
                'deals' => Deal::count(),
                'openDeals' => Deal::whereIn('deal_stage_id', $openStageIds)->count(),
                'pipelineValue' => (float) $pipelineValue,
            ],
            'dealsByStage' => $dealsByStage,
            'upcomingDeals' => $upcomingDeals,
        ]);
    }
}
