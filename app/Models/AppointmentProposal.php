<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentProposal extends Model
{
    protected $fillable = ['appointment_id', 'starts_at', 'ends_at', 'is_accepted'];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'starts_at'   => 'datetime',
            'ends_at'     => 'datetime',
            'is_accepted' => 'boolean',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
