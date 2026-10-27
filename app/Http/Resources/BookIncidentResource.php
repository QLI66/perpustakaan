<?php

namespace App\Http\Resources;

use App\Models\BookIncident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var BookIncident $incident */
        $incident = $this->resource;

        $outstandingAmount = null;
        if ($incident->amount_due !== null) {
            $outstandingAmount = bcsub((string) $incident->amount_due, (string) ($incident->amount_paid ?? 0), 2);
        }

        return [
            'id' => $incident->id,
            'type' => $incident->type,
            'status' => $incident->status,
            'loan_item' => $incident->relationLoaded('loanItem') && $incident->loanItem ? [
                'id' => $incident->loanItem->id,
            ] : null,
            'loan' => $incident->relationLoaded('loanItem') && $incident->loanItem && $incident->loanItem->relationLoaded('loan') && $incident->loanItem->loan ? [
                'id' => $incident->loanItem->loan->id,
                'loan_number' => $incident->loanItem->loan->loan_number,
            ] : null,
            'member' => $incident->relationLoaded('loanItem') && $incident->loanItem && $incident->loanItem->relationLoaded('loan') && $incident->loanItem->loan && $incident->loanItem->loan->relationLoaded('member') && $incident->loanItem->loan->member ? [
                'id' => $incident->loanItem->loan->member->id,
                'member_number' => $incident->loanItem->loan->member->member_number,
                'name' => $incident->loanItem->loan->member->name,
            ] : null,
            'book_copy' => $incident->relationLoaded('bookCopy') && $incident->bookCopy ? [
                'id' => $incident->bookCopy->id,
                'barcode' => $incident->bookCopy->barcode,
            ] : null,
            'book' => $incident->relationLoaded('bookCopy') && $incident->bookCopy && $incident->bookCopy->relationLoaded('book') && $incident->bookCopy->book ? [
                'id' => $incident->bookCopy->book->id,
                'title' => $incident->bookCopy->book->title,
                'isbn' => $incident->bookCopy->book->isbn,
            ] : null,
            'resolution_type' => $incident->resolution_type,
            'replacement_book_copy' => $incident->relationLoaded('replacementBookCopy') && $incident->replacementBookCopy ? [
                'id' => $incident->replacementBookCopy->id,
                'barcode' => $incident->replacementBookCopy->barcode,
            ] : null,
            'amount_due' => $incident->amount_due,
            'amount_paid' => $incident->amount_paid,
            'outstanding_amount' => $outstandingAmount,
            'opened_at' => $incident->opened_at?->toISOString(),
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'resolved_by' => $incident->relationLoaded('resolvedBy') && $incident->resolvedBy ? [
                'id' => $incident->resolvedBy->id,
                'name' => $incident->resolvedBy->name,
            ] : null,
            'notes' => $incident->notes,
        ];
    }
}
