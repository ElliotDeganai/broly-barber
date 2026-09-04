<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'quantity',
        'unit_price_ht', 'unit_price_ttc', 'total_ht', 'total_ttc', 'sold_at',
    ];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return [
            'sold_at'        => 'date',
            'unit_price_ht'  => 'decimal:2',
            'unit_price_ttc' => 'decimal:2',
            'total_ht'       => 'decimal:2',
            'total_ttc'      => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
