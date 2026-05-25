<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'tenant' => $request->user()->currentTenant?->only(['id', 'name', 'slug']),
            'stats' => [
                'entities' => Entity::count(),
                'people' => Person::count(),
                'calendarEvents' => CalendarEvent::count(),
                'deals' => Deal::count(),
            ],
        ]);
    }
}
