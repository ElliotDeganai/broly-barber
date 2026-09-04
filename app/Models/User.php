<?php

namespace App\Models;

use App\Services\LoyaltyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role',
        'provider', 'provider_id', 'is_offline',
        'debt_amount', 'debt_note',
        'is_blocked', 'block_reason', 'blocked_at',
        'visits_count', 'last_completed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at'        => 'datetime',
            'last_completed_at' => 'datetime',
            'is_offline'        => 'boolean',
            'is_blocked'        => 'boolean',
            'debt_amount'       => 'decimal:2',
            'password'          => 'hashed',
        ];
    }

    protected $appends = ['tier', 'is_admin'];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    /** Palier de fidélité dérivé du nombre de visites. */
    public function getTierAttribute(): array
    {
        return app(LoyaltyService::class)->tierFor($this->visits_count ?? 0);
    }

    public function hasDebt(): bool
    {
        return (float) $this->debt_amount > 0;
    }

    public function scopeClients($query)
    {
        return $query->where('role', 'client');
    }

    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    public function scopeInDebt($query)
    {
        return $query->where('debt_amount', '>', 0);
    }
}
