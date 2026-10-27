<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOpnameReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => [
                'nullable',
                'date',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
            'status' => [
                'nullable',
                'string',
                'in:DRAFT,IN_PROGRESS,COMPLETED,CANCELLED',
            ],
            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
        ];
    }
}
