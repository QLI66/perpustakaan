<?php

namespace App\Http\Resources;

use App\Models\LoanItem;
use App\Support\LoanLateness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for a return operation result.
 *
 * $this->resource is the LoanItem returned by ReturnService::returnItem().
 * It has bookCopy already loaded, and fine() is loaded after the return.
 */
class ReturnResource extends JsonResource
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

        $isOverdue = false;
        $overdueDays = 0;
        $overdueHours = 0;

        if ($item->due_at && $item->returned_at) {
            if ($item->duration_unit === 'hour') {
                $overdueHours = LoanLateness::lateHours($item->due_at, $item->returned_at);
                $isOverdue = LoanLateness::isOverdue($item->due_at, $item->returned_at, 'hour');
            } else {
                $overdueDays = LoanLateness::lateDays($item->due_at, $item->returned_at);
                $isOverdue = $overdueDays > 0;
            }
        }

        // Load the fine if it exists
        $fine = $item->fine;

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
                'duration_unit' => $item->duration_unit ?? 'day',
                'duration_value' => $item->duration_value,
                'returned_at' => $item->returned_at?->toISOString(),
                'renewal_count' => $item->renewal_count,
                'status' => $item->status,
                'is_overdue' => $isOverdue,
                'overdue_days' => $overdueDays,
                'overdue_hours' => $overdueHours,
            ],
            'loan_status' => $item->loan?->status,
            'fine' => $fine ? [
                'id' => $fine->id,
                'late_days' => $fine->late_days,
                'rate_per_day' => $fine->rate_per_day,
                'late_unit' => $fine->late_unit,
                'late_units' => $fine->late_units,
                'rate_per_unit' => $fine->rate_per_unit,
                'amount' => $fine->amount,
                'amount_paid' => $fine->amount_paid,
                'status' => $fine->status,
                'assessed_at' => $fine->assessed_at?->toISOString(),
            ] : null,
        ];
    }
}
