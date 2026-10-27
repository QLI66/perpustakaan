<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CatalogBookIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'collection_type_id' => ['nullable', 'integer', 'exists:collection_types,id'],
            'class_level_id' => ['nullable', 'integer', 'exists:class_levels,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
            'available' => ['nullable', 'boolean'],
            'status' => [
                'nullable',
                'string',
                'in:AVAILABLE,BORROWED,RESERVED,LOST,DAMAGED,MAINTENANCE',
            ],
            'sort' => ['nullable', 'string', 'in:title,publication_year,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('available')) {
            $this->merge([
                'available' => filter_var($this->available, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
