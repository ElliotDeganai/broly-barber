<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;

/**
 * Paliers de fidélité. Une visite = un rendez-vous passé en « completed ».
 * Les couleurs alimentent le badge affiché dans l'espace client.
 */
class LoyaltyService
{
    public const TIERS = [
        ['key' => 'bronze',   'label' => 'Bronze',   'min' => 0,  'max' => 10,  'color' => '#C87F3A'],
        ['key' => 'silver',   'label' => 'Argent',   'min' => 11, 'max' => 24,  'color' => '#B8C4CC'],
        ['key' => 'gold',     'label' => 'Or',       'min' => 25, 'max' => 40,  'color' => '#FFD52A'],
        ['key' => 'platinum', 'label' => 'Platine',  'min' => 41, 'max' => 60,  'color' => '#E4EEF2'],
        ['key' => 'diamond',  'label' => 'Diamant',  'min' => 61, 'max' => 100, 'color' => '#69E64B'],
    ];

    public function tierFor(int $visits): array
    {
        $tier = collect(self::TIERS)->last(fn ($t) => $visits >= $t['min']) ?? self::TIERS[0];
        $next = collect(self::TIERS)->first(fn ($t) => $t['min'] > $visits);

        return [
            'key'        => $tier['key'],
            'label'      => $tier['label'],
            'color'      => $tier['color'],
            'visits'     => $visits,
            'next_label' => $next['label'] ?? null,
            'next_at'    => $next['min'] ?? null,
            'remaining'  => $next ? $next['min'] - $visits : null,
            'progress'   => $next
                ? (int) round(($visits - $tier['min']) / max(1, $next['min'] - $tier['min']) * 100)
                : 100,
        ];
    }

    /** Recalcule le compteur après passage d'un rendez-vous en « completed ». */
    public function recount(User $user): void
    {
        $user->forceFill([
            'visits_count'      => Appointment::where('user_id', $user->id)->completed()->count(),
            'last_completed_at' => Appointment::where('user_id', $user->id)->completed()->max('completed_at'),
        ])->save();
    }
}
