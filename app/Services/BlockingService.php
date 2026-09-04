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

        $strikes = Appointment::where('user_id', $user->id)
            ->where('status', Appointment::CANCELLED)
            ->where('cancelled_by', 'client')
            ->where('cancelled_at', '>=', now()->subMonths(6))
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
