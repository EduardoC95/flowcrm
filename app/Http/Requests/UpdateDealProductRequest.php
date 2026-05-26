<?php

namespace App\Http\Requests;

use App\Models\Deal;
use App\Models\DealProduct;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDealProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deal = $this->route('deal');
        $dealProduct = $this->route('dealProduct');

        return $deal instanceof Deal
            && $dealProduct instanceof DealProduct
            && (int) $dealProduct->deal_id === (int) $deal->id
            && ($this->user()?->can('update', $deal) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
