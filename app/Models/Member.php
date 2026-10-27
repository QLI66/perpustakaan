<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'member_number',
        'name',
        'member_type_id',
        'identity_number',
        'class_level_id',
        'major_id',
        'joined_at',
        'status',
        'notes',
        'date_of_birth',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The User account associated with this member (optional).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Type of this member.
     */
    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class);
    }

    /**
     * The Class Level of this member (optional).
     */
    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * The Major of this member (optional).
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /**
     * The loans made by this member.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * The reservations made by this member.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
