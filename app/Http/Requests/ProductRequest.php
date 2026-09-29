<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')->id;
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $money = ['regex:/^\\d{1,10}(\\.\\d{1,2})?$/D'];

        return [
            'name' => [$required, 'string', 'max:255'],
            'sku' => [$required, 'string', 'max:80', Rule::unique('products')->where('organization_id', $organizationId)->ignore($this->route('product'))],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('products')->where('organization_id', $organizationId)->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'purchase_price' => [$required, ...$money],
            'sale_price' => [$required, ...$money],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'minimum_stock' => ['sometimes', 'integer', 'between:0,1000000'],
            'tax_rate_bps' => ['sometimes', 'integer', 'between:0,10000'],
            'active' => ['sometimes', 'boolean'],
            'current_stock' => ['prohibited'], 'organization_id' => ['prohibited'],
        ];
    }
}
