<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\AvailabilityBreak;
use Illuminate\Database\Seeder;

/**
 * Semaine type de départ, à ajuster par le barbier depuis le back office.
 * Convention : 0 = dimanche ... 6 = samedi.
 */
class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        // Ouverture tardive pour rendre l'offre « Last minute » (après 20h) utilisable
        $week = [
            2 => ['10:00', '21:00'],
            3 => ['10:00', '21:00'],
            4 => ['10:00', '21:00'],
            5 => ['10:00', '22:00'],
            6 => ['09:00', '19:00'],
        ];

        foreach ($week as $weekday => [$start, $end]) {
            Availability::updateOrCreate(
                ['weekday' => $weekday, 'start_time' => $start],
                ['end_time' => $end, 'is_open' => true],
            );
        }

        foreach ([2, 3, 4, 5] as $weekday) {
            AvailabilityBreak::updateOrCreate(
                ['weekday' => $weekday, 'start_time' => '13:00'],
                ['end_time' => '14:00', 'label' => 'Pause déjeuner'],
            );
        }
    }
}
