<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:customers,email'],
            'company' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->country)) {
            $this->merge(['country' => strtoupper($this->country)]);
        }
    }
}
