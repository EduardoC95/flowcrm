<?php

namespace App\Observers;

use App\Models\CalendarEvent;
use App\Services\AI\CommercialAgentService;

class CalendarEventObserver
{
    public function created(CalendarEvent $event): void
    {
        app(CommercialAgentService::class)->analyzeRecentActivity($event);
    }

    public function updated(CalendarEvent $event): void
    {
        if ($event->wasChanged('status') && $event->deal_id) {
            app(CommercialAgentService::class)->analyzeRecentActivity($event);
        }
    }
}
