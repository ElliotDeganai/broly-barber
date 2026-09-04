<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Point d'envoi unique des notifications.
 *
 * Deux raisons de passer par ici plutôt que d'appeler notify() directement.
 *
 * D'abord, une adresse manquante ne doit jamais interrompre l'action en cours :
 * un client créé au studio n'a parfois pas d'email, et cela ne peut pas empêcher
 * la confirmation de son rendez-vous.
 *
 * Ensuite, la panne du serveur d'envoi ne doit pas non plus faire échouer une
 * validation déjà enregistrée en base. L'erreur est journalisée, pas propagée.
 */
class Notifier
{
    public static function send(?User $user, Notification $notification): void
    {
        if (! $user?->email) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification non envoyée', [
                'user'         => $user->id,
                'notification' => $notification::class,
                'message'      => $e->getMessage(),
            ]);
        }
    }

    /** Prévient tous les comptes administrateurs du studio. */
    public static function admins(Notification $notification): void
    {
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => self::send($admin, $notification),
        );
    }
}
