<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Prestations et tarifs de la maquette.
 *
 * ⚠ Les intitulés et le contenu exact restent à confirmer avec Fred.
 * Règle actuelle : la coupe courte est proposée SANS restructuration, les
 * autres coupes la comprennent.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'slug'                => 'coupe-courte',
                'name'                => 'Coupe courte',
                'includes'            => 'Tondeuse + Pointe',
                'has_restructuration' => false,
                'price'               => 35,
                'duration_min'        => 30,
                'description'         => 'Travail à la tondeuse avec finition aux pointes. Sans restructuration.',
                'position'            => 1,
            ],
            [
                'slug'                => 'coupe-mi-courte',
                'name'                => 'Coupe mi-courte',
                'includes'            => 'Ciseau + Tondeuse',
                'has_restructuration' => true,
                'price'               => 45,
                'duration_min'        => 45,
                'description'         => 'Ciseau et tondeuse, restructuration comprise.',
                'position'            => 2,
            ],
            [
                'slug'                => 'coupe-mi-longue',
                'name'                => 'Coupe mi-longue',
                'includes'            => 'Ciseau + Tondeuse',
                'has_restructuration' => true,
                'price'               => 55,
                'duration_min'        => 60,
                'description'         => 'Ciseau et tondeuse, restructuration comprise.',
                'position'            => 3,
            ],
            [
                'slug'                => 'coupe-longue',
                'name'                => 'Coupe longue',
                'includes'            => 'Ciseau + Tondeuse',
                'has_restructuration' => true,
                'price'               => 65,
                'duration_min'        => 75,
                'description'         => 'Ciseau et tondeuse sur cheveux longs, restructuration comprise.',
                'position'            => 4,
            ],
            [
                'slug'                => 'barbe',
                'name'                => 'Barbe',
                'includes'            => null,
                'has_restructuration' => false,
                'price'               => 15,
                'duration_min'        => 20,
                'description'         => 'Mise en forme et contours.',
                'position'            => 5,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['slug' => $service['slug']], $service + ['is_active' => true]);
        }
    }
}
