<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCalendarEventData;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarEventRequest extends FormRequest
{
    use ValidatesCalendarEventData;

    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->calendarEventRules();
    }
}
