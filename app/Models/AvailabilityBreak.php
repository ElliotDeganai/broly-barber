<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityBreak extends Model
{
    protected $fillable = ['weekday', 'start_time', 'end_time', 'label'];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
