<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use Inertia\Inertia;

/** Only Sayajin Store : vitrine seule, vente exclusivement au studio. */
class StoreController extends Controller
{
    public function index()
    {
        return Inertia::render('Client/Store', [
            'content'    => SiteSetting::tree('store'),
            'categories' => ProductCategory::with('products')->orderBy('position')->get()
                ->map(fn ($category) => [
                    'id'          => $category->id,
                    'name'        => $category->name,
                    'description' => $category->description,
                    'price'       => $category->price ? (float) $category->price : null,
                    'products'    => $category->products->where('is_published', true)->values()
                        ->map(fn ($p) => [
                            'id'    => $p->id,
                            'name'  => $p->name,
                            'price' => (float) $p->price,
                            'photo' => $p->photo_url,
                        ]),
                ]),
        ]);
    }
}
