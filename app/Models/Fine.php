<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fine extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'loan_item_id',
        'late_days',
        'rate_per_day',
        'late_unit',
        'late_units',
        'rate_per_unit',
        'amount',
        'amount_paid',
        'status',
        'assessed_at',
        'paid_at',
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
            'late_days' => 'integer',
            'rate_per_day' => 'decimal:2',
            'late_units' => 'integer',
            'rate_per_unit' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'assessed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include unpaid fines (or partial).
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['UNPAID', 'PARTIAL']);
    }

    /**
     * Scope a query to only include paid fines.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'PAID');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The loan item this fine belongs to.
     */
    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }

    /**
     * The payments applied to this fine.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(FinePayment::class);
    }
}
