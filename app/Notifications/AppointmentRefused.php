<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Demande refusée — au client. */
class AppointmentRefused extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $a = $this->appointment->loadMissing('service');

        $lines = ['Votre demande de rendez-vous n\'a pas pu être retenue pour ce créneau.'];

        if ($a->admin_note) {
            $lines[] = 'Motif : ' . e($a->admin_note);
        }

        $lines[] = 'D\'autres créneaux restent disponibles.';

        return $this->branded(
            subject: 'Demande de rendez-vous non retenue',
            title: 'Créneau indisponible',
            lines: $lines,
            details: [
                'Prestation' => $a->service->name,
                'Créneau demandé' => $this->humanDate($a->starts_at),
            ],
            action: 'Choisir un autre créneau',
            url: route('booking.services'),
        );
    }
}
