<?php

namespace App\Http\Resources;

use App\Models\LoanItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bookReturnedAt = null;

        if (
            $this->status === 'FULFILLED' &&
            $this->member_id &&
            $this->book_copy_id &&
            $this->fulfilled_at
        ) {
            $loanItem = LoanItem::query()
                ->whereHas('loan', function ($query) {
                    $query
                        ->where('member_id', $this->member_id)
                        ->where('created_at', '>=', $this->fulfilled_at);
                })
                ->where('book_copy_id', $this->book_copy_id)
                ->orderBy('borrowed_at', 'asc')
                ->first();

            if ($loanItem?->status === 'RETURNED') {
                $bookReturnedAt = $loanItem->returned_at;
            }
        }

        return [
            'id' => $this->id,
            'reservation_number' => $this->reservation_number,
            'status' => $this->status,
            'reserved_at' => $this->reserved_at,
            'ready_at' => $this->ready_at,
            'expires_at' => $this->expires_at,
            'fulfilled_at' => $this->fulfilled_at,
            'book_returned_at' => $bookReturnedAt,
            'cancelled_at' => $this->cancelled_at,
            'notes' => $this->notes,

            'book' => $this->whenLoaded('book', fn () => [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'isbn' => $this->book->isbn,
            ]),

            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'member_number' => $this->member->member_number,
            ]),

            'assigned_copy' => $this->whenLoaded('bookCopy', fn () => [
                'id' => $this->bookCopy->id,
                'barcode' => $this->bookCopy->barcode,
                'inventory_number' => $this->bookCopy->inventory_number,
            ]),
        ];
    }
}
