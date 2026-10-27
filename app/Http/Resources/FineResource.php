<?php

namespace App\Http\Resources;

use App\Models\Fine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Fine $fine */
        $fine = $this->resource;

        $outstandingAmount = bcsub((string) $fine->amount, (string) $fine->amount_paid, 2);
        $isAccruing = (bool) $fine->getAttribute('is_accruing');

        return [
            'id' => $isAccruing ? 'accrual-'.$fine->loan_item_id : $fine->id,
            'loan_item_id' => $fine->loan_item_id,
            'loan' => $fine->relationLoaded('loanItem') && $fine->loanItem && $fine->loanItem->relationLoaded('loan') && $fine->loanItem->loan ? [
                'id' => $fine->loanItem->loan->id,
                'loan_number' => $fine->loanItem->loan->loan_number,
            ] : null,
            'member' => $fine->relationLoaded('loanItem') && $fine->loanItem && $fine->loanItem->relationLoaded('loan') && $fine->loanItem->loan && $fine->loanItem->loan->relationLoaded('member') && $fine->loanItem->loan->member ? [
                'id' => $fine->loanItem->loan->member->id,
                'member_number' => $fine->loanItem->loan->member->member_number,
                'name' => $fine->loanItem->loan->member->name,
            ] : null,
            'book' => $fine->relationLoaded('loanItem') && $fine->loanItem && $fine->loanItem->relationLoaded('bookCopy') && $fine->loanItem->bookCopy && $fine->loanItem->bookCopy->relationLoaded('book') && $fine->loanItem->bookCopy->book ? [
                'id' => $fine->loanItem->bookCopy->book->id,
                'title' => $fine->loanItem->bookCopy->book->title,
                'isbn' => $fine->loanItem->bookCopy->book->isbn,
            ] : null,

            'late_days' => $fine->late_days,
            'rate_per_day' => $fine->rate_per_day,
            'late_unit' => $fine->late_unit,
            'late_units' => $fine->late_units,
            'rate_per_unit' => $fine->rate_per_unit,
            'amount' => $fine->amount,
            'amount_paid' => $fine->amount_paid,
            'outstanding_amount' => $outstandingAmount,
            'status' => $fine->status,
            'is_accruing' => $isAccruing,
            'assessed_at' => $fine->assessed_at?->toISOString(),
            'paid_at' => $fine->paid_at?->toISOString(),
            'notes' => $fine->notes,

            'payment_history' => $this->whenLoaded('payments', function () use ($fine) {
                return FinePaymentResource::collection($fine->payments);
            }),
        ];
    }
}
