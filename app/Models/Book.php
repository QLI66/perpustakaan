<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'isbn',
        'title',
        'subtitle',
        'description',
        'publication_year',
        'edition',
        'language',
        'collection_type_id',
        'publisher_id',
        'cover',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'publication_year' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The collection type this book belongs to.
     */
    public function collectionType(): BelongsTo
    {
        return $this->belongsTo(CollectionType::class);
    }

    /**
     * The publisher of this book (nullable).
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * The authors of this book.
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_authors');
    }

    /**
     * The categories of this book.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_categories');
    }

    /**
     * The targets for this book.
     */
    public function targets(): HasMany
    {
        return $this->hasMany(BookTarget::class);
    }

    /**
     * The copies/eksemplar of this book.
     */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    /**
     * The reservations made for this book.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope a query to filter by language.
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }
}
