<?php

namespace App\Http\Requests;

use App\Models\DealProposal;
use Illuminate\Foundation\Http\FormRequest;

class SendDealProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof DealProposal && ($this->user()?->can('send', $proposal) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'max:255'],
            'email_subject' => ['required', 'string', 'max:255'],
            'email_body' => ['required', 'string'],
        ];
    }
}
