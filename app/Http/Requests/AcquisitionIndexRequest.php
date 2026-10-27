<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcquisitionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'in:PURCHASE,DONATION,GRANT,GIFT,OTHER'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            'sort' => [
                'nullable',
                'string',
                'in:acquisition_number,acquisition_date,source_type,source_name,reference_number,total_amount,created_at',
            ],
            'direction' => ['nullable', 'string', 'in:asc,desc'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
