<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'acquisition_date' => [
                'sometimes',
                'date',
            ],

            'source_type' => [
                'sometimes',
                'string',
                'in:PURCHASE,DONATION,GRANT,GIFT,OTHER',
            ],

            'source_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
