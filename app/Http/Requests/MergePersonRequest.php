<?php

namespace App\Http\Requests;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('person');
        $target = Person::find($this->input('target_person_id'));

        return $source instanceof Person
            && $target instanceof Person
            && $this->user()->can('update', $source)
            && $this->user()->can('update', $target)
            && $source->isNot($target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_person_id' => [
                'required',
                Rule::exists('people', 'id')->where('tenant_id', $this->user()->current_tenant_id),
                Rule::notIn([$this->route('person')->id]),
            ],
        ];
    }
}
