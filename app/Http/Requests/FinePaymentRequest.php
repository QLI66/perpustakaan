<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:CASH,TRANSFER,OTHER'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
