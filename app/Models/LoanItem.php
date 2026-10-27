<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'loan_id',
        'book_copy_id',
        'borrowed_at',
        'due_at',
        'duration_unit',
        'duration_value',
        'returned_at',
        'renewal_count',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'duration_value' => 'integer',
            'returned_at' => 'datetime',
            'renewal_count' => 'integer',
        ];
    }

    /**
     * Scope a query to only include active loan items.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The loan transaction that this item belongs to.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * The specific book copy being borrowed.
     */
    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    /**
     * The fine associated with this loan item (if any).
     */
    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class);
    }

    /**
     * The book incident associated with this loan item (if any).
     */
    public function incident(): HasOne
    {
        return $this->hasOne(BookIncident::class);
    }
}
