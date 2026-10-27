<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookTarget extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'book_id',
        'class_level_id',
        'major_id',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The book associated with this target.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * The class level associated with this target (nullable).
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * The major associated with this target (nullable).
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }
}
