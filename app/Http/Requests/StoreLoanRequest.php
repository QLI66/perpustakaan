<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'book_copy_ids' => ['required', 'array', 'min:1'],
            'book_copy_ids.*' => ['required', 'integer', 'exists:book_copies,id'],
            'borrowed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'loan_duration_days' => ['nullable', 'integer', 'min:1', 'max:3'],
            'duration_unit' => ['sometimes', 'required', 'string', 'in:hour,day'],
            'duration_value' => ['sometimes', 'required', 'integer', 'min:1', 'max:3'],
        ];

        // member_id is required for staff/admin, but will be ignored for members
        // in the controller which securely maps to their own member profile.
        // We validate it here for STAFF, but allow it to be nullable strictly for validation purposes.
        if ($this->user()?->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            $rules['member_id'] = ['required', 'integer', 'exists:members,id'];
        } else {
            // For MEMBER, we don't strictly require it in the request payload
            // because the controller overrides it anyway.
            // If they happen to send it, we just ignore it in the controller.
            $rules['member_id'] = ['nullable', 'integer', 'exists:members,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasUnit = $this->exists('duration_unit');
            $hasValue = $this->exists('duration_value');
            $hasLegacyDuration = $this->exists('loan_duration_days');

            if ($hasUnit !== $hasValue) {
                $validator->errors()->add('duration_value', 'Both duration_unit and duration_value are required together.');
            }

            if (($hasUnit || $hasValue) && $hasLegacyDuration) {
                $validator->errors()->add('loan_duration_days', 'Use either the explicit duration fields or loan_duration_days, not both.');
            }

            if ($this->input('duration_unit') === 'hour' && $this->input('duration_value') !== 1 && $this->input('duration_value') !== '1') {
                $validator->errors()->add('duration_value', 'Hourly loans must have a duration of exactly one hour.');
            }
        });
    }
}
