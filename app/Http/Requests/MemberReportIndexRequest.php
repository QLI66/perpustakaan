<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberReportIndexRequest extends FormRequest
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
                'in:ACTIVE,INACTIVE,SUSPENDED',
            ],
        ];
    }
}
