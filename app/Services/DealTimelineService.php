<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealFollowUpEmail;
use App\Models\DealNote;
use App\Models\DealProduct;
use App\Models\DealProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DealTimelineService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function forDeal(Deal $deal, array $filters = []): Collection
    {
        $items = collect()
            ->merge($this->activityLogItems($deal))
            ->merge($this->calendarEventItems($deal))
            ->merge($this->noteItems($deal))
            ->merge($this->proposalItems($deal))
            ->merge($this->followUpEmailItems($deal))
            ->merge($this->productItems($deal));

        return $items
            ->filter(fn (array $item) => $this->matchesFilters($item, $filters))
            ->sortByDesc('occurred_at')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function activityLogItems(Deal $deal): Collection
    {
        return $deal->activityLogs()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(function (ActivityLog $log) {
                $type = $this->typeForLog($log);

                return [
                    'id' => 'activity-log-'.$log->id,
                    'source_type' => 'activity_log',
                    'type' => $type,
                    'title' => $this->labelForAction($log->action),
                    'description' => $log->description,
                    'occurred_at' => $log->created_at?->toDateTimeString(),
                    'user_name' => $log->user?->name,
                    'badge_label' => $this->badgeForType($type),
                    'icon' => $this->iconForType($type),
                    'metadata' => [
                        'action' => $log->action,
                        'module' => $log->module,
                    ],
                    'details' => [
                        'description' => $log->description,
                        'properties' => $log->properties,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function calendarEventItems(Deal $deal): Collection
    {
        return $deal->calendarEvents()
            ->with('owner:id,name,email')
            ->latest('start_at')
            ->get()
            ->map(function (CalendarEvent $event) {
                $startAt = $event->start_at ?? $event->starts_at ?? $event->created_at;

                return [
                    'id' => 'calendar-event-'.$event->id,
                    'source_type' => 'calendar_event',
                    'type' => 'activity',
                    'title' => $event->title,
                    'description' => $event->description ?: $event->notes,
                    'occurred_at' => $startAt?->toDateTimeString(),
                    'user_name' => $event->owner?->name,
                    'badge_label' => $this->eventTypeLabel($event->type),
                    'icon' => $this->iconForEventType($event->type),
                    'metadata' => [
                        'type' => $event->type,
                        'status' => $event->status,
                        'priority' => $event->priority,
                    ],
                    'details' => [
                        'type' => $event->type,
                        'status' => $event->status,
                        'owner' => $event->owner?->name,
                        'start_at' => $startAt?->toDateTimeString(),
                        'end_at' => ($event->end_at ?? $event->ends_at)?->toDateTimeString(),
                        'location' => $event->location,
                        'description' => $event->description ?: $event->notes,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function noteItems(Deal $deal): Collection
    {
        return $deal->dealNotes()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (DealNote $note) => [
                'id' => 'deal-note-'.$note->id,
                'source_type' => 'deal_note',
                'type' => 'note',
                'title' => 'Nota adicionada',
                'description' => Str::limit($note->body, 140),
                'occurred_at' => $note->created_at?->toDateTimeString(),
                'user_name' => $note->user?->name,
                'badge_label' => 'Nota',
                'icon' => 'sticky-note',
                'metadata' => [
                    'note_id' => $note->id,
                ],
                'details' => [
                    'author' => $note->user?->name,
                    'body' => $note->body,
                ],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function proposalItems(Deal $deal): Collection
    {
        return $deal->proposals()
            ->with(['uploader:id,name', 'sender:id,name'])
            ->latest()
            ->get()
            ->map(function (DealProposal $proposal) {
                $occurredAt = $proposal->sent_at ?? $proposal->created_at;
                $title = $proposal->status === DealProposal::STATUS_SENT ? 'Proposta enviada' : 'Proposta adicionada';

                return [
                    'id' => 'deal-proposal-'.$proposal->id,
                    'source_type' => 'deal_proposal',
                    'type' => 'proposal',
                    'title' => $title,
                    'description' => $proposal->original_name,
                    'occurred_at' => $occurredAt?->toDateTimeString(),
                    'user_name' => $proposal->sender?->name ?? $proposal->uploader?->name,
                    'badge_label' => 'Proposta',
                    'icon' => 'paperclip',
                    'metadata' => [
                        'status' => $proposal->status,
                        'recipient_email' => $proposal->recipient_email,
                    ],
                    'details' => [
                        'file_name' => $proposal->original_name,
                        'mime_type' => $proposal->mime_type,
                        'size' => $proposal->size,
                        'status' => $proposal->status,
                        'uploaded_by' => $proposal->uploader?->name,
                        'sent_by' => $proposal->sender?->name,
                        'sent_at' => $proposal->sent_at?->toDateTimeString(),
                        'recipient_email' => $proposal->recipient_email,
                        'email_subject' => $proposal->email_subject,
                        'email_body' => $proposal->email_body,
                    ],
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function followUpEmailItems(Deal $deal): Collection
    {
        return $deal->followUpEmails()
            ->with(['template:id,name', 'sender:id,name'])
            ->latest('sent_at')
            ->get()
            ->map(fn (DealFollowUpEmail $email) => [
                'id' => 'follow-up-email-'.$email->id,
                'source_type' => 'deal_follow_up_email',
                'type' => 'follow_up',
                'title' => $email->subject,
                'description' => Str::limit($email->body, 140),
                'occurred_at' => $email->sent_at?->toDateTimeString() ?? $email->created_at?->toDateTimeString(),
                'user_name' => $email->sender?->name,
                'badge_label' => 'Follow-up',
                'icon' => 'mail',
                'metadata' => [
                    'recipient_email' => $email->recipient_email,
                    'template' => $email->template?->name,
                ],
                'details' => [
                    'recipient_email' => $email->recipient_email,
                    'subject' => $email->subject,
                    'body' => $email->body,
                    'sent_at' => $email->sent_at?->toDateTimeString(),
                    'template' => $email->template?->name,
                ],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function productItems(Deal $deal): Collection
    {
        return $deal->dealProducts()
            ->with('product:id,name,sku')
            ->latest()
            ->get()
            ->map(fn (DealProduct $dealProduct) => [
                'id' => 'deal-product-'.$dealProduct->id,
                'source_type' => 'deal_product',
                'type' => 'product',
                'title' => 'Produto no negócio',
                'description' => $dealProduct->product?->name,
                'occurred_at' => $dealProduct->created_at?->toDateTimeString(),
                'user_name' => null,
                'badge_label' => 'Produto',
                'icon' => 'package',
                'metadata' => [
                    'product_id' => $dealProduct->product_id,
                    'sku' => $dealProduct->product?->sku,
                ],
                'details' => [
                    'product' => $dealProduct->product?->name,
                    'sku' => $dealProduct->product?->sku,
                    'quantity' => (float) $dealProduct->quantity,
                    'unit_price' => (float) $dealProduct->unit_price,
                    'total' => (float) $dealProduct->total,
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $filters
     */
    private function matchesFilters(array $item, array $filters): bool
    {
        if (($filters['type'] ?? null) && $filters['type'] !== 'all' && $item['type'] !== $filters['type']) {
            return false;
        }

        if (($filters['date_from'] ?? null) && $item['occurred_at'] < $filters['date_from'].' 00:00:00') {
            return false;
        }

        if (($filters['date_to'] ?? null) && $item['occurred_at'] > $filters['date_to'].' 23:59:59') {
            return false;
        }

        if ($search = $filters['search'] ?? null) {
            $haystack = Str::lower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '');

            return Str::contains($haystack, Str::lower((string) $search));
        }

        return true;
    }

    private function typeForLog(ActivityLog $log): string
    {
        if ($log->module === 'deal_products' || Str::contains($log->action, 'deal_product')) {
            return 'product';
        }

        if (Str::contains($log->action, 'proposal')) {
            return 'proposal';
        }

        if (Str::contains($log->action, 'follow_up')) {
            return 'follow_up';
        }

        return 'change';
    }

    private function badgeForType(string $type): string
    {
        return [
            'activity' => 'Atividade',
            'email' => 'Email',
            'follow_up' => 'Follow-up',
            'note' => 'Nota',
            'product' => 'Produto',
            'proposal' => 'Proposta',
            'change' => 'Alteração',
            'system' => 'Sistema',
        ][$type] ?? 'Registo';
    }

    private function iconForType(string $type): string
    {
        return [
            'activity' => 'calendar',
            'email' => 'mail',
            'follow_up' => 'mail',
            'note' => 'sticky-note',
            'product' => 'package',
            'proposal' => 'paperclip',
            'change' => 'history',
            'system' => 'settings',
        ][$type] ?? 'circle';
    }

    private function iconForEventType(?string $type): string
    {
        return [
            'call' => 'phone',
            'meeting' => 'users',
            'note' => 'sticky-note',
            'reminder' => 'bell',
            'task' => 'check-square',
        ][$type ?? ''] ?? 'calendar';
    }

    private function eventTypeLabel(?string $type): string
    {
        return [
            'call' => 'Chamada',
            'meeting' => 'Reunião',
            'note' => 'Nota',
            'reminder' => 'Lembrete',
            'task' => 'Tarefa',
        ][$type ?? ''] ?? 'Atividade';
    }

    private function labelForAction(string $action): string
    {
        return Str::of($action)
            ->replace(['_', '.'], ' ')
            ->headline()
            ->toString();
    }
}
