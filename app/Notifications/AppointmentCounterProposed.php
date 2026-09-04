<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Créneaux alternatifs proposés — au client. */
class AppointmentCounterProposed extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $a = $this->appointment->loadMissing(['service', 'proposals']);

        $lines = ["Le créneau demandé n'est pas disponible, mais le barber vous en propose d'autres."];

        if ($a->admin_note) {
            $lines[] = 'Message du barber : « ' . e($a->admin_note) . ' »';
        }

        // Les créneaux sont numérotés plutôt que listés en vrac : le client
        // doit pouvoir les comparer d'un coup d'œil.
        $details = [];

        foreach ($a->proposals as $i => $proposal) {
            $details['Proposition ' . ($i + 1)] = $this->humanDate($proposal->starts_at);
        }

        return $this->branded(
            subject: 'Nouveaux créneaux proposés',
            title: 'Le barber vous propose un autre créneau',
            lines: $lines,
            details: $details,
            action: 'Choisir un créneau',
            url: route('account'),
            note: "Le rendez-vous n'est confirmé qu'après votre choix. Si aucun ne convient, vous pouvez les refuser et faire une nouvelle demande.",
            preview: $a->proposals->count() . ' créneau(x) au choix',
        );
    }
}
