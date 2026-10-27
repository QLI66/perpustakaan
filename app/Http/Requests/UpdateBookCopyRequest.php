<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $copyId = $this->route('book_copy')?->id;

        return [
            'book_id' => ['sometimes', 'required', 'integer', 'exists:books,id'],
            'barcode' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('book_copies', 'barcode')->ignore($copyId)],
            'inventory_number' => ['nullable', 'string', 'max:100', Rule::unique('book_copies', 'inventory_number')->ignore($copyId)],
            'location_id' => ['sometimes', 'required', 'integer', 'exists:locations,id'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_source' => ['nullable', 'string', 'in:PURCHASE,DONATION,GRANT,GIFT,OTHER'],
            'acquisition_price' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', 'string', 'in:NEW,GOOD,FAIR,POOR'],
            'notes' => ['nullable', 'string'],
            // NOTE: 'status' is intentionally excluded.
            // Status transitions are handled exclusively by domain services:
            // LoanService, ReturnService, BookIncidentService.
        ];
    }
}
