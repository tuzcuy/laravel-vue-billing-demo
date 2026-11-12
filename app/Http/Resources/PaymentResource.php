<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount_cents' => (int) $this->amount_cents,
            'method' => $this->method,
            'paid_at' => $this->paid_at->toDateString(),
            'reference' => $this->reference,
        ];
    }
}
