<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookCopyResource extends JsonResource
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
            'barcode' => $this->barcode,
            'inventory_number' => $this->inventory_number,
            'book' => $this->whenLoaded('book', fn () => [
                'id' => $this->book->id,
                'isbn' => $this->book->isbn,
                'title' => $this->book->title,
            ]),
            'location' => $this->whenLoaded('location', fn () => $this->location ? [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
            ] : null),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_source' => $this->acquisition_source,
            'acquisition_price' => $this->acquisition_price,
            'condition' => $this->condition,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
