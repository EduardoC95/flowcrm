<?php

namespace App\Http\Requests;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuickDealActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal && ($this->user()?->can('update', $deal) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->current_tenant_id;

        return [
            'type' => ['required', Rule::in(['note', 'task', 'call', 'meeting', 'reminder'])],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'owner_id' => ['nullable', Rule::exists('tenant_user', 'user_id')->where('tenant_id', $tenantId)],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            if ($type === 'note' && ! $this->filled('body') && ! $this->filled('description')) {
                $validator->errors()->add('body', 'Escreve o conteúdo da nota.');
            }

            if ($type !== 'note') {
                foreach (['title', 'start_at', 'owner_id'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, 'Este campo é obrigatório para atividades.');
                    }
                }
            }
        });
    }
}
