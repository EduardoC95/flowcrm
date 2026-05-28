<?php

namespace App\Http\Requests;

use App\Models\AISuggestion;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ConvertAISuggestionToActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $suggestion = $this->route('suggestion');

        return $suggestion instanceof AISuggestion && Gate::allows('convertToActivity', $suggestion);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'priority' => ['nullable', Rule::in(AISuggestion::PRIORITIES)],
            'activity_type' => ['nullable', Rule::in([
                CalendarEvent::TYPE_TASK,
                CalendarEvent::TYPE_CALL,
                CalendarEvent::TYPE_MEETING,
                CalendarEvent::TYPE_REMINDER,
            ])],
        ];
    }
}
