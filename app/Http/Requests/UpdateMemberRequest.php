<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('members', 'user_id')->ignore($memberId)],
            'member_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('members', 'member_number')->ignore($memberId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'member_type_id' => ['sometimes', 'required', 'integer', 'exists:member_types,id'],
            'identity_number' => ['nullable', 'string', 'max:50', Rule::unique('members', 'identity_number')->ignore($memberId)],
            'class_level_id' => ['nullable', 'integer', 'exists:class_levels,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
            'joined_at' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,SUSPENDED'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
