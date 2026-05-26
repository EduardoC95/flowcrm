<?php

namespace App\Console\Commands;

use App\Jobs\SendCalendarEventReminderJob;
use App\Models\CalendarEvent;
use Illuminate\Console\Command;

class SendCalendarEventRemindersCommand extends Command
{
    protected $signature = 'calendar:send-reminders';

    protected $description = 'Send due FlowCRM calendar event reminders.';

    public function handle(): int
    {
        $events = CalendarEvent::query()
            ->whereNotNull('reminder_at')
            ->where('reminder_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->where('status', CalendarEvent::STATUS_PENDING)
            ->get(['id']);

        $events->each(fn (CalendarEvent $event) => SendCalendarEventReminderJob::dispatchSync($event->id));

        $this->info("Sent {$events->count()} calendar reminder(s).");

        return self::SUCCESS;
    }
}
