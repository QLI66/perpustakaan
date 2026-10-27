<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoanIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'status' => ['nullable', 'string', 'in:ACTIVE,COMPLETED,CANCELLED'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'overdue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:loan_number,borrowed_at,status,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('overdue')) {
            $this->merge([
                'overdue' => filter_var($this->overdue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
