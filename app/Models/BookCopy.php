<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'book_id',
        'barcode',
        'inventory_number',
        'location_id',
        'acquisition_date',
        'acquisition_source',
        'acquisition_price',
        'condition',
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
            'acquisition_date' => 'date',
            'acquisition_price' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The book this copy belongs to.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * The location where this copy is placed.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Acquisition item that created this physical book copy.
     */
    public function acquisitionItem(): BelongsTo
    {
        return $this->belongsTo(AcquisitionItem::class, 'acquisition_item_id');
    }

    /**
     * The loan items (borrowing history) associated with this copy.
     */
    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    /**
     * Book incidents associated with this copy.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(BookIncident::class, 'book_copy_id');
    }

    /**
     * Reservations that have been assigned to this copy.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'book_copy_id');
    }
}
