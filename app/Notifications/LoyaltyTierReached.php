<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Nouveau palier de fidélité atteint — au client. */
class LoyaltyTierReached extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    /** @param array{label: string, visits: int, next_label: ?string, remaining: ?int} $tier */
    public function __construct(private array $tier) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $lines = [
            "Vous venez d'atteindre le palier <strong>{$this->tier['label']}</strong>.",
        ];

        if ($this->tier['next_label'] ?? null) {
            $lines[] = "Encore {$this->tier['remaining']} visite(s) avant le palier {$this->tier['next_label']}.";
        } else {
            $lines[] = 'C\'est le palier le plus élevé du studio.';
        }

        return $this->branded(
            subject: "Palier {$this->tier['label']} atteint",
            title: 'Nouveau palier de fidélité',
            lines: $lines,
            details: [
                'Palier'  => $this->tier['label'],
                'Visites' => (string) $this->tier['visits'],
            ],
            action: 'Voir mon espace',
            url: route('account'),
        );
    }
}
