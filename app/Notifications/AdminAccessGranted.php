<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Ouverture d'un accès au back-office — au nouvel administrateur.
 *
 * Le mot de passe n'est volontairement pas dans l'email : un secret qui
 * circule par email reste lisible dans la boîte de réception indéfiniment.
 * Il est transmis de vive voix par la personne qui a créé le compte.
 */
class AdminAccessGranted extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        return $this->branded(
            subject: 'Votre accès à la gestion du studio',
            title: 'Accès administrateur ouvert',
            lines: [
                'Un accès à la gestion du studio vient d\'être ouvert sur votre adresse.',
                'Vous pourrez y suivre les rendez-vous, les disponibilités, la boutique et les ventes.',
            ],
            details: [
                'Identifiant' => $notifiable->email,
            ],
            action: 'Se connecter',
            url: route('login'),
            note: 'Le mot de passe vous est communiqué directement par le studio. '
                . 'Si vous ne l\'avez pas, utilisez « Mot de passe oublié » sur la page de connexion.',
        );
    }
}
