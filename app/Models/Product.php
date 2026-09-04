<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'photo_path',
        'price', 'vat_rate', 'is_published', 'position',
    ];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'price'        => 'decimal:2',
            'vat_rate'     => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    protected $appends = ['photo_url'];

    protected static function booted(): void
    {
        static::saving(fn (Product $p) => $p->slug = $p->slug ?: Str::slug($p->name));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ProductSale::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->orderBy('position');
    }

    public function priceHt(): float
    {
        return round((float) $this->price / (1 + (float) $this->vat_rate / 100), 2);
    }
}
