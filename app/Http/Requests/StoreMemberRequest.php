<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:members,user_id'],
            'member_number' => ['required', 'string', 'max:50', 'unique:members,member_number'],
            'name' => ['required', 'string', 'max:255'],
            'member_type_id' => ['required', 'integer', 'exists:member_types,id'],
            'identity_number' => ['nullable', 'string', 'max:50', 'unique:members,identity_number'],
            'class_level_id' => ['nullable', 'integer', 'exists:class_levels,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
            'joined_at' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,SUSPENDED'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
