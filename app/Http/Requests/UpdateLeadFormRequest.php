<?php

namespace App\Http\Requests;

use App\Models\LeadForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateLeadFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leadForm = $this->route('lead_form');

        return $leadForm instanceof LeadForm
            && ($this->user()?->can('update', $leadForm) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leadForm = $this->route('lead_form');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('lead_forms', 'slug')->ignore($leadForm?->id)],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:100', 'alpha_dash:ascii'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(LeadForm::FIELD_TYPES)],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['nullable', 'string', 'max:255'],
            'confirmation_message' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'require_captcha' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
        ]);
    }
}
