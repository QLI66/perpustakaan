<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IncidentReportIndexRequest extends FormRequest
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

            'member_id' => [
                'nullable',
                'integer',
                'exists:members,id',
            ],

            'type' => [
                'nullable',
                'string',
                'in:LOST,DAMAGED',
            ],

            'status' => [
                'nullable',
                'string',
                'in:OPEN,RESOLVED',
            ],
        ];
    }
}
