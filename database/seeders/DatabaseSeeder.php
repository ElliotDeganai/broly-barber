<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrateur. `php artisan db:seed` joue tout dans l'ordre des dépendances.
 * Chaque seeder est idempotent : relançable sans créer de doublon.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,      // sections, contenu, réglages
            AdminUserSeeder::class,     // compte du barbier
            ServiceSeeder::class,       // prestations et tarifs
            AvailabilitySeeder::class,  // semaine type
            StoreSeeder::class,         // Only Sayajin Store
            FaqSeeder::class,           // foire aux questions
            PageSeeder::class,          // « Qui suis-je » et pages légales
        ]);

        // DemoDataSeeder n'est PAS appelé ici : il se lance à la demande, en
        // local uniquement, pour ne jamais risquer de polluer la production.
        //   sail artisan db:seed --class=DemoDataSeeder
    }
}
