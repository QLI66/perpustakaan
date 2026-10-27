<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'username', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Spatie uses this to determine the default guard for permission checks.
     * We force 'web' here because our DB seeds use 'web' guard.
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The Member profile associated with this user.
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * The fine payments received by this staff user.
     */
    public function receivedFinePayments(): HasMany
    {
        return $this->hasMany(FinePayment::class, 'received_by');
    }

    /**
     * Book incidents resolved by this staff user.
     */
    public function resolvedBookIncidents(): HasMany
    {
        return $this->hasMany(BookIncident::class, 'resolved_by');
    }
}
