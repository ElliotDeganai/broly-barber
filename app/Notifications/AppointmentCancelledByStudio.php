<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Le studio annule un rendez-vous confirmé — au client. */
class AppointmentCancelledByStudio extends Notification implements ShouldQueue
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

        $lines = ['Votre rendez-vous a dû être annulé par le studio. Nous en sommes désolés.'];

        if ($a->admin_note) {
            $lines[] = 'Motif : ' . e($a->admin_note);
        }

        $lines[] = 'Vous pouvez reprendre un créneau dès maintenant.';

        return $this->branded(
            subject: 'Rendez-vous annulé par le studio',
            title: 'Votre rendez-vous est annulé',
            lines: $lines,
            details: [
                'Prestation' => $a->service->name,
                'Créneau'    => $this->humanDate($a->starts_at),
            ],
            action: 'Choisir un nouveau créneau',
            url: route('booking.services'),
        );
    }
}
