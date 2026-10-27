<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'biography',
        'birth_date',
        'death_date',
        'nationality',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'is_active' => 'boolean',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope a query to only include active authors.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The books written by this author.
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_authors');
    }
}
