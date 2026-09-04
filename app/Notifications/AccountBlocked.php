<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Suspension de la réservation en ligne — au client.
 *
 * Le motif interne n'est pas transmis : ce que le barbier note pour lui-même
 * n'a pas vocation à être lu par le client.
 */
class AccountBlocked extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        return $this->branded(
            subject: 'Réservation en ligne suspendue',
            title: 'Accès à la réservation suspendu',
            lines: [
                'La réservation en ligne n\'est momentanément plus disponible sur votre compte.',
                'Votre historique et vos rendez-vous passés restent consultables dans votre espace.',
            ],
            action: 'Contacter le studio',
            url: route('contact'),
            note: 'Pour rétablir votre accès, prenez contact directement avec le studio.',
        );
    }
}
