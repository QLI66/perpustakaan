<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opname_number' => $this->opname_number,
            'opname_date' => $this->opname_date?->toDateString(),

            'location' => $this->whenLoaded(
                'location',
                fn () => [
                    'id' => $this->location->id,
                    'code' => $this->location->code,
                    'name' => $this->location->name,
                ]
            ),

            'location_id' => $this->location_id,

            'status' => $this->status,

            'creator' => $this->whenLoaded(
                'creator',
                fn () => [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ]
            ),

            'created_by' => $this->created_by,

            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            'notes' => $this->notes,

            'items' => $this->whenLoaded(
                'items',
                fn () => $this->items->map(
                    fn ($item) => [
                        'id' => $item->id,
                        'book_copy_id' => $item->book_copy_id,

                        'book_copy' => $item->relationLoaded('bookCopy')
                            ? [
                                'id' => $item->bookCopy->id,
                                'barcode' => $item->bookCopy->barcode,
                                'inventory_number' => $item->bookCopy->inventory_number,
                                'status' => $item->bookCopy->status,
                                'condition' => $item->bookCopy->condition,

                                'book' => $item->bookCopy->relationLoaded('book')
                                    ? [
                                        'id' => $item->bookCopy->book->id,
                                        'title' => $item->bookCopy->book->title,
                                    ]
                                    : null,

                                'location' => $item->bookCopy->relationLoaded('location')
                                    ? [
                                        'id' => $item->bookCopy->location->id,
                                        'code' => $item->bookCopy->location->code,
                                        'name' => $item->bookCopy->location->name,
                                    ]
                                    : null,
                            ]
                            : null,

                        'expected_status' => $item->expected_status,
                        'actual_status' => $item->actual_status,

                        'expected_condition' => $item->expected_condition,
                        'actual_condition' => $item->actual_condition,

                        'result' => $item->result,

                        'checked_at' => $item->checked_at?->toISOString(),
                        'checked_by' => $item->checked_by,

                        'checker' => $item->relationLoaded('checkedBy')
                            && $item->checkedBy !== null
                            ? [
                                'id' => $item->checkedBy->id,
                                'name' => $item->checkedBy->name,
                                'email' => $item->checkedBy->email,
                            ]
                            : null,

                        'notes' => $item->notes,

                        'created_at' => $item->created_at?->toISOString(),
                        'updated_at' => $item->updated_at?->toISOString(),
                    ]
                )
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
