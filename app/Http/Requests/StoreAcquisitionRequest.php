<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acquisition_date' => [
                'required',
                'date',
            ],

            'source_type' => [
                'required',
                'string',
                'in:PURCHASE,DONATION,GRANT,GIFT,OTHER',
            ],

            'source_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.book_id' => [
                'required',
                'integer',
                'exists:books,id',
                'distinct',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.unit_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
