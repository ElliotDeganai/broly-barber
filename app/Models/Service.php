<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Service extends Model
{
    /**
     * Couleurs de repli, attribuées par identifiant.
     *
     * Choisies pour rester lisibles sur le fond sombre du back-office et
     * distinctes les unes des autres une fois réduites à un pavé de planning.
     */
    public const PALETTE = [
        '#69E64B', '#4BC8E6', '#B47BFF', '#FFD52A',
        '#FF8A3D', '#FF6B9D', '#5B8CFF', '#2ED9A3',
    ];

    protected $fillable = [
        'name', 'slug', 'includes', 'description', 'has_restructuration',
        'price', 'duration_min', 'image_path', 'image_before_path', 'color', 'is_active', 'position',
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

    protected $appends = ['image_url', 'image_before_url', 'display_color'];

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

    /**
     * Couleur effectivement utilisée par le planning.
     *
     * Une prestation sans couleur choisie en reçoit une de la palette plutôt
     * que du gris : deux prestations voisines restent distinguables dès la
     * première ouverture du calendrier, sans réglage préalable.
     */
    public function getDisplayColorAttribute(): string
    {
        return $this->color ?: self::PALETTE[((int) $this->id) % count(self::PALETTE)];
    }

    /** URL absolue : les vues n'ont jamais à préfixer /storage elles-mêmes. */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /** Visuel « avant » : facultatif, et sans lui l'animation ne s'affiche pas. */
    public function getImageBeforeUrlAttribute(): ?string
    {
        return $this->image_before_path ? asset('storage/' . $this->image_before_path) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('position');
    }
}
