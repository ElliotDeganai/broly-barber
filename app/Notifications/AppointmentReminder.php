<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\SiteSetting;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Rappel la veille du rendez-vous — au client. */
class AppointmentReminder extends Notification implements ShouldQueue
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

        $details = [
            'Prestation' => $a->service->name,
            'Date'       => $this->humanDate($a->starts_at),
            'À régler'   => $this->money($a->total_due),
        ];

        $lines = ['Petit rappel : votre rendez-vous a lieu demain.'];

        if ($address = SiteSetting::get('studio_address')) {
            $lines[] = 'Adresse : <strong>' . e($address) . '</strong>';
        }

        return $this->branded(
            subject: 'Rappel — rendez-vous demain',
            title: 'Votre rendez-vous approche',
            lines: $lines,
            details: $details,
            action: 'Voir mon rendez-vous',
            url: route('account'),
            note: 'En cas d\'empêchement, prévenez le studio dès que possible. Un retard important peut conduire à adapter ou reporter la prestation.',
            preview: $this->humanDate($a->starts_at),
        );
    }
}
