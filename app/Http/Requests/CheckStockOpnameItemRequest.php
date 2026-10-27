<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckStockOpnameItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => [
                'required',
                'string',
                Rule::in([
                    'FOUND',
                    'MISSING',
                    'DIFFERENT',
                ]),
            ],

            'actual_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'AVAILABLE',
                    'BORROWED',
                    'RESERVED',
                    'LOST',
                    'DAMAGED',
                ]),
            ],

            'actual_condition' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'NEW',
                    'GOOD',
                    'FAIR',
                    'POOR',
                ]),
            ],

            'checked_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
