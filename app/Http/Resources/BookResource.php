<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'publication_year' => $this->publication_year,
            'edition' => $this->edition,
            'language' => $this->language,
            'cover' => $this->cover,

            'collection_type' => $this->whenLoaded(
                'collectionType',
                fn () => [
                    'id' => $this->collectionType->id,
                    'code' => $this->collectionType->code,
                    'name' => $this->collectionType->name,
                ]
            ),

            'publisher' => $this->whenLoaded(
                'publisher',
                fn () => $this->publisher ? [
                    'id' => $this->publisher->id,
                    'name' => $this->publisher->name,
                ] : null
            ),

            'authors' => $this->whenLoaded(
                'authors',
                fn () => $this->authors->map(
                    fn ($author) => [
                        'id' => $author->id,
                        'name' => $author->name,
                    ]
                )->values()
            ),

            'categories' => $this->whenLoaded(
                'categories',
                fn () => $this->categories->map(
                    fn ($category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ]
                )->values()
            ),

            'targets' => $this->whenLoaded(
                'targets',
                fn () => $this->targets->map(
                    fn ($target) => [
                        'id' => $target->id,
                        'class_level' => $target->classLevel?->name,
                        'major' => $target->major?->name,
                    ]
                )->values()
            ),

            'copies_count' => $this->when(
                isset($this->copies_count),
                $this->copies_count
            ),

            'available_copies_count' => $this->when(
                isset($this->available_copies_count),
                $this->available_copies_count
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
