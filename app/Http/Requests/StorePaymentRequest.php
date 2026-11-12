<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:'.implode(',', Payment::METHODS)],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
