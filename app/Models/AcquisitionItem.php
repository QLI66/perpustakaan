<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'acquisition_id',
        'book_id',
        'quantity',
        'unit_price',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Acquisition transaction this item belongs to.
     */
    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(Acquisition::class);
    }

    /**
     * Book this item is acquiring.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Physical book copies created from this acquisition item.
     */
    public function bookCopies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'acquisition_item_id');
    }
}
