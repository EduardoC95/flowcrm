<?php

namespace App\Jobs;

use App\Mail\CalendarEventReminderMail;
use App\Models\CalendarEvent;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCalendarEventReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $calendarEventId) {}

    public function handle(ActivityLogger $logger): void
    {
        $event = CalendarEvent::query()
            ->with('owner:id,name,email')
            ->whereKey($this->calendarEventId)
            ->whereNull('reminder_sent_at')
            ->where('status', CalendarEvent::STATUS_PENDING)
            ->first();

        if (! $event || ! $event->owner?->email) {
            return;
        }

        Mail::to($event->owner->email)->send(new CalendarEventReminderMail($event));

        $event->forceFill([
            'reminder_sent_at' => now(),
        ])->save();

        $logger->log(
            'calendar_event.reminder_sent',
            'calendar_events',
            $event->tenant_id,
            $event,
            'Calendar event reminder sent.',
            ['owner_id' => $event->owner_id],
        );
    }
}
