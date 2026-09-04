<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/** Section du back office : « Accueil — Bandeau », « Règles de réservation »... */
class SettingGroup extends Model
{
    protected $fillable = ['key', 'label', 'help', 'scope', 'position'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('setting_groups'));
        static::deleted(fn () => Cache::forget('setting_groups'));
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SiteSetting::class, 'group', 'key')->orderBy('position');
    }
}
