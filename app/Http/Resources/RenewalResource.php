<?php

namespace App\Http\Resources;

use App\Models\LoanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a renewal operation result.
 */
class RenewalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoanItem $item */
        $item = $this->resource;

        return [
            'loan' => [
                'id' => $item->loan_id,
                'loan_number' => $item->loan?->loan_number,
                'status' => $item->loan?->status,
            ],
            'loan_item' => [
                'id' => $item->id,
                'book_copy' => $item->relationLoaded('bookCopy') && $item->bookCopy ? [
                    'id' => $item->bookCopy->id,
                    'barcode' => $item->bookCopy->barcode,
                    'status' => $item->bookCopy->status,
                    'book' => $item->bookCopy->relationLoaded('book') && $item->bookCopy->book ? [
                        'id' => $item->bookCopy->book->id,
                        'title' => $item->bookCopy->book->title,
                        'isbn' => $item->bookCopy->book->isbn,
                    ] : null,
                    'location' => $item->bookCopy->relationLoaded('location') && $item->bookCopy->location ? [
                        'id' => $item->bookCopy->location->id,
                        'code' => $item->bookCopy->location->code,
                        'name' => $item->bookCopy->location->name,
                    ] : null,
                ] : null,
                'borrowed_at' => $item->borrowed_at?->toISOString(),
                'due_at' => $item->due_at?->toISOString(),
                'renewal_count' => $item->renewal_count,
                'status' => $item->status,
            ],
        ];
    }
}
