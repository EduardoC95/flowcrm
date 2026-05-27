<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuickDealActivityRequest;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealNote;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class QuickDealActivityController extends Controller
{
    public function store(StoreQuickDealActivityRequest $request, Deal $deal, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $data = $request->validated();

        if ($data['type'] === 'note') {
            $note = DealNote::create([
                'tenant_id' => $deal->tenant_id,
                'deal_id' => $deal->id,
                'user_id' => $request->user()->id,
                'body' => $data['body'] ?? $data['description'] ?? '',
            ]);

            $logger->log(
                'deal_note.created',
                'deal_notes',
                $deal->tenant_id,
                $deal,
                'Deal note created.',
                ['deal_note_id' => $note->id],
            );
        } else {
            $event = CalendarEvent::create([
                'tenant_id' => $deal->tenant_id,
                'eventable_type' => Deal::class,
                'eventable_id' => $deal->id,
                'deal_id' => $deal->id,
                'entity_id' => $deal->entity_id,
                'person_id' => $deal->person_id,
                'owner_id' => $data['owner_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? $data['body'] ?? null,
                'notes' => $data['description'] ?? $data['body'] ?? null,
                'type' => $data['type'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'starts_at' => $data['start_at'],
                'ends_at' => $data['end_at'] ?? null,
                'status' => CalendarEvent::STATUS_PENDING,
                'priority' => $data['priority'] ?? $deal->priority,
            ]);

            $logger->log(
                'quick_activity.created',
                'calendar_events',
                $deal->tenant_id,
                $deal,
                'Quick activity created for deal.',
                [
                    'calendar_event_id' => $event->id,
                    'type' => $event->type,
                    'owner_id' => $event->owner_id,
                ],
            );
        }

        $deal->forceFill(['last_activity_at' => now()])->save();

        return back()->with('success', 'Atividade adicionada à cronologia.');
    }
}
