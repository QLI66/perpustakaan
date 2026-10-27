<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'renewed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // All other fields (member_id, book_copy_id, due_at, renewal_count, etc.)
            // are NOT accepted. RenewalService defines authoritative state.
        ];
    }
}
