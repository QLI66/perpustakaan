<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'barcode' => ['required', 'string', 'max:100', 'unique:book_copies,barcode'],
            'inventory_number' => ['nullable', 'string', 'max:100', 'unique:book_copies,inventory_number'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_source' => ['nullable', 'string', 'in:PURCHASE,DONATION,GRANT,GIFT,OTHER'],
            'acquisition_price' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', 'string', 'in:NEW,GOOD,FAIR,POOR'],
            'notes' => ['nullable', 'string'],
        ];
        // NOTE: 'status' is intentionally NOT in this request.
        // New copies always start as AVAILABLE — enforced in the controller.
    }
}
