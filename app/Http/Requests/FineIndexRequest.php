<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FineIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'loan_item_id' => ['nullable', 'integer', 'exists:loan_items,id'],
            'status' => ['nullable', 'string', 'in:UNPAID,PARTIAL,PAID'],
            'payment_status' => ['nullable', 'string', 'in:UNPAID,PARTIAL,PAID'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'overdue_only' => ['nullable', 'boolean'],

            'sort' => ['nullable', 'string', 'in:assessed_at,amount,amount_paid,status,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
