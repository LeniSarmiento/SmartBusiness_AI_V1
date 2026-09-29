<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('organization')->id;

        return [
            'request_key' => ['required', 'uuid'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('organization_id', $id)->whereNull('deleted_at')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('organization_id', $id)->whereNull('deleted_at')->where('active', true)],
            'items.*.quantity' => ['required', 'integer', 'between:1,10000'],
            'total' => ['prohibited'], 'subtotal' => ['prohibited'], 'tax' => ['prohibited'], 'seller_id' => ['prohibited'], 'organization_id' => ['prohibited'],
        ];
    }
}
