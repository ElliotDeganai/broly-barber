<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/** Only Sayajin Store : trois gammes, d'après la maquette. */
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'slug'     => 'cires-brillantes',
                'name'     => 'Cires brillantes',
                'price'    => 15,
                'position' => 1,
                'products' => [
                    ['name' => 'Pomme — Broly Wax',   'price' => 15],
                    ['name' => 'Kiwi — Vegeta Wax',   'price' => 15],
                    ['name' => 'Fraise — Goku Wax',   'price' => 15],
                ],
            ],
            [
                'slug'     => 'cire-matte',
                'name'     => 'Cire matte',
                'price'    => 20,
                'position' => 2,
                'products' => [
                    ['name' => 'Avocat — Full Power', 'price' => 20],
                ],
            ],
            [
                'slug'     => 'poudre-full-powder',
                'name'     => 'Poudre Full Powder',
                'price'    => 20,
                'position' => 3,
                'products' => [
                    ['name' => 'Full Powder', 'price' => 20],
                ],
            ],
        ];

        foreach ($groups as $group) {
            $products = $group['products'];
            unset($group['products']);

            $category = ProductCategory::updateOrCreate(['slug' => $group['slug']], $group);

            foreach ($products as $i => $product) {
                Product::updateOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($product['name'])],
                    $product + [
                        'category_id'  => $category->id,
                        'vat_rate'     => 20,
                        'is_published' => true,
                        'position'     => $i,
                    ],
                );
            }
        }
    }
}
