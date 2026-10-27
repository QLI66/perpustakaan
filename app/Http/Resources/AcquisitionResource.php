<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcquisitionResource extends JsonResource
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
            'acquisition_number' => $this->acquisition_number,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'source_type' => $this->source_type,
            'source_name' => $this->source_name,
            'reference_number' => $this->reference_number,
            'total_amount' => $this->total_amount,
            'created_by' => $this->created_by,
            'notes' => $this->notes,

            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'book_id' => $item->book_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'notes' => $item->notes,

                        'book' => $item->relationLoaded('book')
                            ? [
                                'id' => $item->book->id,
                                'title' => $item->book->title,
                                'isbn' => $item->book->isbn,
                            ]
                            : null,

                        'book_copies' => $item->relationLoaded('bookCopies')
                            ? $item->bookCopies->map(fn ($copy) => [
                                'id' => $copy->id,
                                'barcode' => $copy->barcode,
                                'inventory_number' => $copy->inventory_number,
                                'location_id' => $copy->location_id,
                                'status' => $copy->status,
                                'condition' => $copy->condition,
                            ])->values()
                            : null,
                    ];
                })->values();
            }),
        ];
    }
}
