<?php

namespace App\Notifications;

use App\Models\MagicLink;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Lien de connexion à usage unique — au client. */
class MagicLinkRequested extends Notification implements ShouldQueue
{
    use Queueable, BuildsBrandedMail;

    public function __construct(private MagicLink $link) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $minutes = (int) now()->diffInMinutes($this->link->expires_at, absolute: true);

        return $this->branded(
            subject: 'Votre lien de connexion',
            title: 'Connectez-vous en un clic',
            lines: [
                'Voici votre lien d\'accès à votre espace client. Aucun mot de passe n\'est nécessaire.',
            ],
            action: 'Accéder à mon espace',
            url: route('magic.consume', $this->link->token),
            note: "Ce lien est valable {$minutes} minutes et ne fonctionne qu'une seule fois. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            preview: "Valable {$minutes} minutes",
        );
    }
}
