<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\SiteSetting;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Rendez-vous confirmé — au client. */
class AppointmentConfirmed extends Notification implements ShouldQueue
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
        $cancelHours = (int) SiteSetting::get('cancellation_hours', 12);

        $details = [
            'Prestation' => $a->service->name,
            'Date'       => $this->humanDate($a->starts_at),
            'Durée'      => $a->duration_min . ' min',
            'À régler'   => $this->money($a->total_due),
        ];

        if ($a->debt_snapshot > 0) {
            $details['Dont reliquat'] = $this->money($a->debt_snapshot);
        }

        $lines = ['Votre rendez-vous est confirmé. Le créneau vous est réservé.'];

        // L'adresse n'est communiquée qu'ici : le studio est privé, c'est la
        // règle annoncée dans la FAQ.
        if ($address = SiteSetting::get('studio_address')) {
            $lines[] = 'Adresse du studio : <strong>' . e($address) . '</strong>';
        }

        return $this->branded(
            subject: 'Rendez-vous confirmé — ' . $this->humanDate($a->starts_at),
            title: 'Votre rendez-vous est confirmé',
            lines: $lines,
            details: $details,
            action: 'Voir mon rendez-vous',
            url: route('account'),
            note: "Annulation possible jusqu'à {$cancelHours} h avant le rendez-vous, depuis votre espace client. Venez de préférence les cheveux propres et sans produit coiffant.",
            preview: $a->service->name . ' · ' . $this->humanDate($a->starts_at),
        );
    }
}
