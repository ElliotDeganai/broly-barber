<?php

namespace App\Services;

use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tarification.
 *
 * Le prix vient de la prestation elle-même — 35 € coupe courte, 45 € mi-courte,
 * 55 € mi-longue, 65 € longue, 15 € barbe. Un créneau situé après l'heure de
 * bascule (20 h par défaut) applique le multiplicateur « Last minute ».
 *
 * Le total affiché ajoute la dette éventuelle : c'est ce que le client règle au
 * studio. Recalculé à l'affichage ET à la validation — jamais repris du
 * formulaire, qui n'est pas une source de confiance.
 */
class PricingService
{
    public function quote(User $user, Service $service, ?CarbonImmutable $startsAt = null): array
    {
        $lateHour   = (int) SiteSetting::get('late_night_hour', 20);
        $multiplier = (float) SiteSetting::get('late_night_multiplier', 2);

        $isLate = $startsAt !== null && $startsAt->hour >= $lateHour;
        $price  = (float) $service->price * ($isLate ? $multiplier : 1);
        $debt   = (float) $user->debt_amount;

        return [
            'base_price'    => round((float) $service->price, 2),
            'service_price' => round($price, 2),
            'is_late_night' => $isLate,
            'late_hour'     => $lateHour,
            'multiplier'    => $multiplier,
            'debt'          => round($debt, 2),
            'total_due'     => round($price + $debt, 2),
        ];
    }
}
