<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCalendarEventData;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarEventRequest extends FormRequest
{
    use ValidatesCalendarEventData;

    public function authorize(): bool
    {
        $calendarEvent = $this->route('calendar_event') ?? $this->route('calendarEvent');

        return $calendarEvent instanceof CalendarEvent && ($this->user()?->can('update', $calendarEvent) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->calendarEventRules();
    }
}
