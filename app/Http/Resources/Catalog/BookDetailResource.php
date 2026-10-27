<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $copies = $this->whenLoaded('copies');
        $availableCopies = $copies ? $copies->where('status', 'AVAILABLE') : collect();

        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'publication_year' => $this->publication_year,
            'edition' => $this->edition,
            'language' => $this->language,
            'collection_type' => $this->whenLoaded('collectionType', fn () => [
                'id' => $this->collectionType->id,
                'code' => $this->collectionType->code,
                'name' => $this->collectionType->name,
                'description' => $this->collectionType->description,
            ]),
            'publisher' => $this->whenLoaded('publisher', fn () => [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
                'address' => $this->publisher->address,
                'website' => $this->publisher->website,
            ]),
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'name' => $author->name,
                'biography' => $author->biography,
            ])),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
            ])),
            'targets' => $this->whenLoaded('targets', fn () => $this->targets->map(fn ($target) => [
                'id' => $target->id,
                'class_level' => $target->class_level_id ? $target->classLevel->name : null,
                'major' => $target->major_id ? $target->major->name : null,
            ])),
            'availability' => $copies ? [
                'total_copies' => $copies->count(),
                'available_copies' => $copies->where('status', 'AVAILABLE')->count(),
                'borrowed_copies' => $copies->where('status', 'BORROWED')->count(),
                'reserved_copies' => $copies->where('status', 'RESERVED')->count(),
                'lost_copies' => $copies->where('status', 'LOST')->count(),
                'damaged_copies' => $copies->where('status', 'DAMAGED')->count(),
                'maintenance_copies' => $copies->where('status', 'MAINTENANCE')->count(),
                'is_available' => $copies->where('status', 'AVAILABLE')->count() > 0,
            ] : null,
            'locations' => $availableCopies->map(function ($copy) {
                if ($copy->relationLoaded('location') && $copy->location) {
                    return [
                        'id' => $copy->location->id,
                        'code' => $copy->location->code,
                        'name' => $copy->location->name,
                    ];
                }

                return null;
            })->filter()->unique('id')->values()->all(),
        ];
    }
}
