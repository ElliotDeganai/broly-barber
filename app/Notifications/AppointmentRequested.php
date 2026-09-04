<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\SiteSetting;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Nouvelle demande de rendez-vous — au barbier. */
class AppointmentRequested extends Notification implements ShouldQueue
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
        $hours = (int) SiteSetting::get('response_hours', 24);

        $details = [
            'Client'    => $a->user->name,
            'Prestation'=> $a->service->name,
            'Créneau'   => $this->humanDate($a->starts_at),
            'Durée'     => $a->duration_min . ' min',
            'Montant'   => $this->money($a->total_due),
        ];

        if ($a->user->phone) {
            $details['Téléphone'] = $a->user->phone;
        }

        // La dette est rappelée ici : c'est au moment de valider que le barbier
        // doit savoir qu'il y a un reliquat à encaisser.
        if ($a->debt_snapshot > 0) {
            $details['Dont dette'] = $this->money($a->debt_snapshot);
        }

        $lines = ["<strong>{$a->user->name}</strong> demande un rendez-vous."];

        if ($a->is_late_night) {
            $lines[] = 'Créneau de dernière minute : le tarif appliqué est doublé.';
        }

        if ($a->client_comment) {
            $lines[] = 'Message du client : « ' . e($a->client_comment) . ' »';
        }

        return $this->branded(
            subject: "Nouvelle demande — {$a->user->name}",
            title: 'Nouvelle demande de rendez-vous',
            lines: $lines,
            details: $details,
            action: 'Voir la demande',
            url: route('admin.appointments.index', [
                'appointment' => $a->id,
                // Le filtre est porté par l'URL : il reste visible dans la
                // barre d'adresse et survit à un rechargement.
                'day'         => $a->starts_at->toDateString(),
            ]),
            note: "Le client attend une réponse sous {$hours} h.",
            preview: $this->humanDate($a->starts_at) . ' · ' . $a->service->name,
        );
    }
}
