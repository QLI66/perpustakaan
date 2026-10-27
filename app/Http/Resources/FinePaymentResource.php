<?php

namespace App\Http\Resources;

use App\Models\FinePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FinePayment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at?->toISOString(),
            'payment_method' => $payment->payment_method,
            'reference_number' => $payment->reference_number,
            'received_by' => $payment->relationLoaded('receivedBy') && $payment->receivedBy ? [
                'id' => $payment->receivedBy->id,
                'name' => $payment->receivedBy->name,
            ] : null,
            'notes' => $payment->notes,
        ];
    }
}
