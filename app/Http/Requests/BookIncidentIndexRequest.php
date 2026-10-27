<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookIncidentIndexRequest extends FormRequest
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
            'book_copy_id' => ['nullable', 'integer', 'exists:book_copies,id'],
            'type' => ['nullable', 'string', 'in:LOST,DAMAGED'],
            'status' => ['nullable', 'string', 'in:OPEN,RESOLVED'],
            'resolution_type' => ['nullable', 'string', 'in:REPLACED_BOOK,PAID_MONEY'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            'sort' => ['nullable', 'string', 'in:opened_at,resolved_at,type,status,amount_due,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
