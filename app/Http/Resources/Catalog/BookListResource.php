<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'publication_year' => $this->publication_year,
            'edition' => $this->edition,
            'language' => $this->language,
            'collection_type' => $this->whenLoaded('collectionType', fn () => [
                'id' => $this->collectionType->id,
                'code' => $this->collectionType->code,
                'name' => $this->collectionType->name,
            ]),
            'publisher' => $this->whenLoaded('publisher', fn () => [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ]),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'name' => $author->name,
            ])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($target) => [
                'id' => $target->id,
                'class_level' => $target->class_level_id ? $target->classLevel->name : null,
                'major' => $target->major_id ? $target->major->name : null,
            ])),
            'availability' => [
                'total_copies' => $this->copies_count ?? 0,
                'available_copies' => $this->available_copies_count ?? 0,
                'is_available' => ($this->available_copies_count ?? 0) > 0,
            ],
        ];
    }
}
