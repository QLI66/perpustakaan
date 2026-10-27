<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOpnameIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'DRAFT',
                    'IN_PROGRESS',
                    'COMPLETED',
                    'CANCELLED',
                ]),
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'opname_number',
                    'opname_date',
                    'status',
                    'created_at',
                ]),
            ],

            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
