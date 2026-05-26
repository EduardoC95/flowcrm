<?php

namespace App\Http\Requests\Concerns;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCalendarEventData
{
    /**
     * @return array<string, mixed>
     */
    protected function calendarEventRules(): array
    {
        $tenantId = $this->user()?->current_tenant_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(CalendarEvent::TYPES)],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['required', Rule::exists('tenant_user', 'user_id')->where('tenant_id', $tenantId)],
            'priority' => ['nullable', Rule::in(CalendarEvent::PRIORITIES)],
            'status' => ['nullable', Rule::in(CalendarEvent::STATUSES)],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:start_at'],
            'eventable_type' => ['nullable', Rule::in(['entity', 'person', 'deal'])],
            'eventable_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('eventable_id') && ! $this->input('eventable_type')) {
                $validator->errors()->add('eventable_type', 'Seleciona o tipo de registo associado.');
            }

            if ($this->input('eventable_type') && ! $this->input('eventable_id')) {
                $validator->errors()->add('eventable_id', 'Seleciona o registo associado.');
            }

            $this->validateEventableTenant($validator);
        });
    }

    private function validateEventableTenant(Validator $validator): void
    {
        if (! $this->input('eventable_type') || ! $this->input('eventable_id')) {
            return;
        }

        $class = match ($this->input('eventable_type')) {
            'entity' => Entity::class,
            'person' => Person::class,
            'deal' => Deal::class,
            default => null,
        };

        if (! $class) {
            return;
        }

        $record = $class::query()->find($this->input('eventable_id'));

        if (! $record || (int) $record->tenant_id !== (int) $this->user()?->current_tenant_id) {
            $validator->errors()->add('eventable_id', 'O registo associado não pertence ao tenant ativo.');
        }
    }
}
