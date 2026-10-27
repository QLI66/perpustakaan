<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // All other fields (book_copy_id, member_id, due_at, late_days, amount, rate_per_day)
            // are explicitly NOT accepted. ReturnService is the authoritative source.
        ];
    }
}
