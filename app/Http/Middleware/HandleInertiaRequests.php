<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use App\Support\ImagePayload;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Auth, permissions et réglages partagés à toutes les pages. */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'is_admin'    => $user->is_admin,
                    'is_blocked'  => $user->is_blocked,
                    'debt_amount' => (float) $user->debt_amount,
                    'tier'        => $user->tier,
                ] : null,

                'permissions' => [
                    'book'           => $user && ! $user->is_admin && ! $user->is_blocked,
                    'manage_content' => (bool) $user?->is_admin,
                    'manage_clients' => (bool) $user?->is_admin,
                    'view_stats'     => (bool) $user?->is_admin,
                ],
            ],

            // all_cached() résout les images en URL absolues : le visuel du
            // bandeau est ainsi disponible partout, y compris sur les écrans
            // d'authentification qui n'ont pas de contrôleur à eux.
            'settings' => fn () => SiteSetting::all_cached(),

            // Fond commun à toutes les pages publiques. Partagé ici plutôt que
            // renvoyé par chaque contrôleur : le layout en a besoin partout.
            'background' => fn () => ImagePayload::make(
                SiteSetting::where('key', 'home_hero_image')->value('value'),
            ),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
