<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Gamme de la boutique : « Cires brillantes », « Cire matte », « Poudre ». */
class ProductCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'position'];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $c) => $c->slug = $c->slug ?: Str::slug($c->name));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id')->orderBy('position');
    }
}
