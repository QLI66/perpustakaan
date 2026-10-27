<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Major extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope a query to only include active majors.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The members associated with this major.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * The book targets associated with this major.
     */
    public function bookTargets(): HasMany
    {
        return $this->hasMany(BookTarget::class);
    }
}
