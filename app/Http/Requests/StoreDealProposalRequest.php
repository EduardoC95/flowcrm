<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Models\DealProposal;
use Illuminate\Foundation\Http\FormRequest;

class StoreDealProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal && ($this->user()?->can('create', [DealProposal::class, $deal]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proposal' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
