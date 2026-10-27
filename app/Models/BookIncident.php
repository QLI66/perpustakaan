<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookIncident extends Model
{
    protected $fillable = [
        'loan_item_id',
        'book_copy_id',
        'type',
        'status',
        'resolution_type',
        'replacement_book_copy_id',
        'amount_due',
        'amount_paid',
        'opened_at',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'RESOLVED');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function replacementBookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'replacement_book_copy_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
