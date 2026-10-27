<?php

namespace App\Http\Resources;

use App\Support\LoanLateness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $asOf = $this->status === 'ACTIVE' ? now() : $this->returned_at;
        $overdueDays = $this->duration_unit === 'hour'
            ? 0
            : LoanLateness::lateDays($this->due_at, $asOf);
        $overdueHours = $this->duration_unit === 'hour'
            ? LoanLateness::lateHours($this->due_at, $asOf)
            : 0;

        $isOverdue = LoanLateness::isOverdue($this->due_at, $asOf, $this->duration_unit);

        return [
            'id' => $this->id,
            'book_copy' => $this->whenLoaded('bookCopy', fn () => [
                'id' => $this->bookCopy->id,
                'barcode' => $this->bookCopy->barcode,
                'book' => $this->bookCopy->relationLoaded('book') && $this->bookCopy->book ? [
                    'id' => $this->bookCopy->book->id,
                    'title' => $this->bookCopy->book->title,
                    'isbn' => $this->bookCopy->book->isbn,
                ] : null,
                'location' => $this->bookCopy->relationLoaded('location') && $this->bookCopy->location ? [
                    'id' => $this->bookCopy->location->id,
                    'code' => $this->bookCopy->location->code,
                    'name' => $this->bookCopy->location->name,
                ] : null,
            ]),
            'borrowed_at' => $this->borrowed_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'duration_unit' => $this->duration_unit ?? 'day',
            'duration_value' => $this->duration_value,
            'returned_at' => $this->returned_at?->toISOString(),
            'renewal_count' => $this->renewal_count,
            'status' => $this->status,
            'is_overdue' => $isOverdue,
            'overdue_days' => $overdueDays,
            'overdue_hours' => $overdueHours,
            'fine' => $this->whenLoaded('fine', fn () => $this->fine ? [
                'id' => $this->fine->id,
                'late_days' => $this->fine->late_days,
                'rate_per_day' => $this->fine->rate_per_day,
                'late_unit' => $this->fine->late_unit,
                'late_units' => $this->fine->late_units,
                'rate_per_unit' => $this->fine->rate_per_unit,
                'amount' => $this->fine->amount,
                'amount_paid' => $this->fine->amount_paid,
                'status' => $this->fine->status,
            ] : null),
        ];
    }
}
