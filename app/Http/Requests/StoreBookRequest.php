<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isbn' => [
                'nullable',
                'string',
                'max:20',
                'unique:books,isbn',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'subtitle' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'publication_year' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],

            'edition' => [
                'nullable',
                'string',
                'max:50',
            ],

            'language' => [
                'nullable',
                'string',
                'max:10',
            ],

            'collection_type_id' => [
                'required',
                'integer',
                'exists:collection_types,id',
            ],

            'publisher_id' => [
                'nullable',
                'integer',
                'exists:publishers,id',
            ],

            'cover' => [
                'nullable',
                'string',
                'max:255',
            ],

            'author_ids' => [
                'sometimes',
                'array',
            ],

            'author_ids.*' => [
                'integer',
                'exists:authors,id',
            ],

            'category_ids' => [
                'sometimes',
                'array',
            ],

            'category_ids.*' => [
                'integer',
                'exists:categories,id',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('language')) {
            $this->merge([
                'language' => 'id',
            ]);
        }
    }
}
