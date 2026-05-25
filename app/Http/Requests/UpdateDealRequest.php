<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDealRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'entity_id' => ['nullable', Rule::exists('entities', 'id')->where('tenant_id', $tenantId)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('tenant_id', $tenantId)],
            'owner_id' => ['required', Rule::exists('tenant_user', 'user_id')->where('tenant_id', $tenantId)],
            'deal_stage_id' => ['required', Rule::exists('deal_stages', 'id')->where('tenant_id', $tenantId)],
            'value' => ['nullable', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(Deal::PRIORITIES)],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->input('entity_id') && ! $this->input('person_id')) {
                $validator->errors()->add('entity_id', 'Seleciona uma entidade ou uma pessoa.');
                $validator->errors()->add('person_id', 'Seleciona uma entidade ou uma pessoa.');
            }

            $this->validatePersonEntityConsistency($validator);
        });
    }

    private function validatePersonEntityConsistency(Validator $validator): void
    {
        if (! $this->input('person_id') || ! $this->input('entity_id')) {
            return;
        }

        $person = Person::find($this->input('person_id'));
        $entity = Entity::find($this->input('entity_id'));

        if ($person?->entity_id && $entity && (int) $person->entity_id !== (int) $entity->id) {
            $validator->errors()->add('person_id', 'A pessoa selecionada pertence a outra entidade.');
        }
    }
}
