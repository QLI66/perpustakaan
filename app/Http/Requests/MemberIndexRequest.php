<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'member_type_id' => ['nullable', 'integer', 'exists:member_types,id'],
            'class_level_id' => ['nullable', 'integer', 'exists:class_levels,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,SUSPENDED'],
            'sort' => ['nullable', 'string', 'in:member_number,name,joined_at,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
