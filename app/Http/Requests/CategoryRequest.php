<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255', Rule::unique('categories')->where('organization_id', $this->route('organization')->id)->ignore($this->route('category'))], 'description' => ['nullable', 'string', 'max:500'], 'active' => ['sometimes', 'boolean'], 'organization_id' => ['prohibited']];
    }
}
