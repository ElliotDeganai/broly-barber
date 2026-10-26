<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SiteSetting;
use App\Models\User;

/** Blocage client : automatique au-delà d'un seuil, ou manuel par le barbier. */
class BlockingService
{
    public function shouldAutoBlock(User $user): bool
    {
        $threshold = (int) SiteSetting::get('auto_block_threshold', 3);

        if ($threshold <= 0) {
            return false;
        }

        // L'absence sans prévenir pèse autant qu'une annulation tardive :
        // dans les deux cas le créneau est perdu pour le studio.
        $strikes = Appointment::where('user_id', $user->id)
            ->where('cancelled_at', '>=', now()->subMonths(6))
            ->where(fn ($q) => $q
                ->where(fn ($c) => $c->where('status', Appointment::CANCELLED)->where('cancelled_by', 'client'))
                ->orWhere('status', Appointment::NO_SHOW))
            ->count();

        return $strikes >= $threshold;
    }

    public function block(User $user, ?string $reason = null): void
    {
        $user->forceFill([
            'is_blocked'   => true,
            'block_reason' => $reason,
            'blocked_at'   => now(),
        ])->save();
    }

    public function unblock(User $user): void
    {
        $user->forceFill([
            'is_blocked'   => false,
            'block_reason' => null,
            'blocked_at'   => null,
        ])->save();
    }
}
