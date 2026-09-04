<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Connexion via Instagram, Facebook ou TikTok.
 * Nécessite les clés dans config/services.php et les URL de redirection
 * déclarées côté plateforme.
 */
class SocialLoginController extends Controller
{
    public function redirect(string $provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect()->route('client.login')->with('error', 'Connexion impossible. Réessayez.');
        }

        // Rattachement à un compte existant si l'email correspond : un client
        // créé au studio par le barbier retrouve ainsi son historique.
        $user = User::where('provider', $provider)->where('provider_id', $social->getId())->first()
            ?? ($social->getEmail() ? User::where('email', $social->getEmail())->first() : null);

        if (! $user) {
            $user = User::create([
                'name'     => $social->getName() ?: $social->getNickname() ?: 'Client',
                'email'    => $social->getEmail(),
                'password' => Str::random(40),
                'role'     => 'client',
            ]);
        }

        $user->forceFill([
            'provider'    => $provider,
            'provider_id' => $social->getId(),
            'is_offline'  => false,
        ])->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('account'));
    }
}
