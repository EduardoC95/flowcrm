<?php

namespace App\Http\Requests;

use App\Models\AISuggestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PostponeAISuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $suggestion = $this->route('suggestion');

        return $suggestion instanceof AISuggestion && Gate::allows('postpone', $suggestion);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'postponed_until' => ['required', 'date', 'after:now'],
        ];
    }
}
