<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = [
        'name', 'slug', 'includes', 'description', 'has_restructuration',
        'price', 'duration_min', 'image_path', 'is_active', 'position',
    ];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'has_restructuration' => 'boolean',
            'price'               => 'decimal:2',
            'duration_min'        => 'integer',
        ];
    }

    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        static::saving(fn (Service $s) => $s->slug = $s->slug ?: Str::slug($s->name));
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    /** URL absolue : les vues n'ont jamais à préfixer /storage elles-mêmes. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('position');
    }
}
