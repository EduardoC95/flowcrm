<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use App\Models\CalendarEvent;
use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AutomationRule::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', Rule::in(AutomationRule::TRIGGERS)],
            'inactivity_days' => ['required_if:trigger_type,'.AutomationRule::TRIGGER_DEAL_INACTIVITY, 'integer', 'min:1', 'max:365'],
            'action_type' => ['required', Rule::in(AutomationRule::ACTIONS)],
            'action_payload' => ['nullable', 'array'],
            'action_payload.activity_type' => ['nullable', Rule::in([
                CalendarEvent::TYPE_TASK,
                CalendarEvent::TYPE_CALL,
                CalendarEvent::TYPE_MEETING,
                CalendarEvent::TYPE_REMINDER,
            ])],
            'action_payload.activity_title_template' => ['nullable', 'string', 'max:255'],
            'action_payload.activity_description_template' => ['nullable', 'string'],
            'action_payload.due_in_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'action_payload.priority' => ['nullable', Rule::in([
                'inherit',
                Deal::PRIORITY_LOW,
                Deal::PRIORITY_MEDIUM,
                Deal::PRIORITY_HIGH,
                Deal::PRIORITY_URGENT,
            ])],
            'notify_owner' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
