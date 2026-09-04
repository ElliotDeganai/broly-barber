<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    protected $fillable = ['weekday', 'start_time', 'end_time', 'is_open'];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'weekday' => 'integer'];
    }
}
