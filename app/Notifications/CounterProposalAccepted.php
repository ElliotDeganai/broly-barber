<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Le client a retenu l'un des créneaux proposés — au barbier. */
class CounterProposalAccepted extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $a = $this->appointment->loadMissing(['user', 'service']);

        return $this->branded(
            subject: "Créneau accepté — {$a->user->name}",
            title: 'Contre-proposition acceptée',
            lines: ["<strong>{$a->user->name}</strong> a retenu l'un des créneaux que vous avez proposés. Le rendez-vous est confirmé."],
            details: [
                'Client'     => $a->user->name,
                'Prestation' => $a->service->name,
                'Créneau'    => $this->humanDate($a->starts_at),
                'Durée'      => $a->duration_min . ' min',
            ],
            action: 'Voir le planning',
            url: route('admin.appointments.index', [
                'appointment' => $a->id,
                // Le filtre est porté par l'URL : il reste visible dans la
                // barre d'adresse et survit à un rechargement.
                'day'         => $a->starts_at->toDateString(),
            ]),
        );
    }
}
