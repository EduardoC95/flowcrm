<?php

namespace App\Http\Requests;

use App\Models\AIChatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExecuteAIChatActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AIChatConversation && Gate::allows('view', $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['create_note', 'create_activity'])],
            'payload' => ['required', 'array'],
            'payload.deal_id' => ['required', 'integer'],
            'payload.body' => ['nullable', 'string', 'max:5000'],
            'payload.title' => ['nullable', 'string', 'max:255'],
            'payload.description' => ['nullable', 'string'],
            'payload.activity_type' => ['nullable', Rule::in(['task', 'call', 'meeting', 'reminder'])],
            'payload.owner_id' => ['nullable', 'integer'],
            'payload.start_at' => ['nullable', 'date'],
            'payload.end_at' => ['nullable', 'date', 'after_or_equal:payload.start_at'],
            'payload.priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ];
    }
}
