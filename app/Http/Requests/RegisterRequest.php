<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(12)->mixedCase()->numbers()], 'organization_name' => ['required', 'string', 'max:120'], 'currency' => ['sometimes', 'in:COP,USD,EUR,MXN,PEN'], 'timezone' => ['sometimes', 'timezone']];
    }
}
