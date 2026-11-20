<?php

namespace App\Http\Requests;

use App\Models\Organization;
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
        $orgId = $this->route('organization') instanceof Organization
            ? $this->route('organization')->id
            : $this->route('organization');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('organization_id', $orgId)
                    ->where('active', true)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', 'in:entry,exit,adjustment'],
            'quantity' => ['required', 'integer', 'between:1,1000000'],
            'direction' => ['required_if:type,adjustment', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
