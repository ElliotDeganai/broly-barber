<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Annulation par le client — au barbier, pour libérer le créneau. */
class AppointmentCancelledByClient extends Notification implements ShouldQueue
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
        $hours = (int) now()->diffInHours($a->starts_at, absolute: true);

        $lines = ["<strong>{$a->user->name}</strong> vient d'annuler son rendez-vous. Le créneau est de nouveau disponible."];

        // Une annulation à moins de 24 h mérite d'être signalée : c'est elle
        // qui alimente le seuil de blocage automatique.
        if ($a->starts_at->isFuture() && $hours < 24) {
            $lines[] = "Annulation tardive : moins de {$hours} h avant le rendez-vous.";
        }

        return $this->branded(
            subject: "Annulation — {$a->user->name}",
            title: 'Rendez-vous annulé',
            lines: $lines,
            details: [
                'Client'     => $a->user->name,
                'Prestation' => $a->service->name,
                'Créneau'    => $this->humanDate($a->starts_at),
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
