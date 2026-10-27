<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                'in:AVAILABLE,BORROWED,RESERVED,LOST,DAMAGED,MAINTENANCE',
            ],

            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
        ];
    }
}
