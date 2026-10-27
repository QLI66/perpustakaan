<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acquisition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'acquisition_number',
        'acquisition_date',
        'source_type',
        'source_name',
        'reference_number',
        'total_amount',
        'created_by',
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
            'acquisition_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * User who created the acquisition record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Items included in this acquisition.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AcquisitionItem::class);
    }
}
