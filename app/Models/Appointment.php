<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const PENDING          = 'pending';
    public const CONFIRMED        = 'confirmed';
    public const REFUSED          = 'refused';
    public const CANCELLED        = 'cancelled';
    public const COUNTER_PROPOSED = 'counter_proposed';
    public const COUNTER_ACCEPTED = 'counter_accepted';
    public const COUNTER_REFUSED  = 'counter_refused';
    public const COMPLETED        = 'completed';

    /** Statuts qui occupent le fauteuil : utilisés pour l'anti-chevauchement. */
    public const BLOCKING = [
        self::PENDING, self::CONFIRMED, self::COUNTER_ACCEPTED, self::COMPLETED,
    ];

    protected $fillable = [
        'user_id', 'service_id', 'starts_at', 'ends_at', 'duration_min', 'status',
        'service_price', 'is_late_night', 'debt_snapshot', 'total_due',
        'client_comment', 'admin_note',
        'terms_accepted_at', 'terms_accepted_ip',
        'confirmed_at', 'cancelled_at', 'completed_at', 'cancelled_by', 'reminded_at',
    ];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'starts_at'         => 'datetime',
            'ends_at'           => 'datetime',
            'terms_accepted_at' => 'datetime',
            'confirmed_at'      => 'datetime',
            'cancelled_at'      => 'datetime',
            'completed_at'      => 'datetime',
            'reminded_at'       => 'datetime',
            'is_late_night'     => 'boolean',
            'service_price'     => 'decimal:2',
            'debt_snapshot'     => 'decimal:2',
            'total_due'         => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(AppointmentProposal::class);
    }

    public function scopeBlocking($query)
    {
        return $query->whereIn('status', self::BLOCKING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::COMPLETED);
    }

    /** Le client peut-il encore annuler ? Délai paramétrable en back office. */
    public function isCancellable(): bool
    {
        $hours = (int) SiteSetting::get('cancellation_hours', 12);

        return in_array($this->status, [self::PENDING, self::CONFIRMED, self::COUNTER_ACCEPTED], true)
            && $this->starts_at->subHours($hours)->isFuture();
    }
}
