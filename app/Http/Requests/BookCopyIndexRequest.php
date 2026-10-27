<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookCopyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', 'string', 'in:AVAILABLE,BORROWED,RESERVED,LOST,DAMAGED,MAINTENANCE'],
            'condition' => ['nullable', 'string', 'in:NEW,GOOD,FAIR,POOR'],
            'acquisition_source' => ['nullable', 'string', 'in:PURCHASE,DONATION,GRANT,GIFT,OTHER'],
            'available' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:barcode,inventory_number,acquisition_date,acquisition_price,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('available')) {
            $this->merge([
                'available' => filter_var($this->available, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
