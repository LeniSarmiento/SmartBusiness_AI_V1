<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('organization_id', $this->route('organization')->id)->whereNull('deleted_at')], 'type' => ['required', 'in:entry,exit,adjustment'], 'quantity' => ['required', 'integer', 'between:1,1000000'], 'direction' => ['required_if:type,adjustment', 'in:increase,decrease'], 'reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
