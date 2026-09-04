<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\User;
use App\Notifications\MagicLinkRequested;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Accès des clients créés au studio, via un lien à usage unique.
 * La réponse est volontairement identique que le compte existe ou non :
 * sinon le formulaire permettrait d'énumérer les adresses enregistrées.
 */
class MagicLinkController extends Controller
{
    public function create()
    {
        if (auth()->check()) {
            return redirect()->route('account');
        }

        return Inertia::render('Auth/ClientLogin');
    }

    public function send(Request $request)
    {
        //dd($request);
        $data = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30'],
        ]);

        $user = User::query()
            ->when($data['email'] ?? null, fn ($q, $e) => $q->where('email', $e))
            ->when($data['phone'] ?? null, fn ($q, $p) => $q->orWhere('phone', $p))
            ->first();
        //dd($user);
        // La raison d'un non-envoi est journalisée, jamais affichée : le message
        // rendu au visiteur reste identique dans tous les cas, sinon le
        // formulaire permettrait d'énumérer les comptes enregistrés.
        $reason = match (true) {
            ! $user            => 'aucun compte pour cette adresse',
            $user->is_admin    => 'compte administrateur — la connexion passe par /login',
            ! $user->email     => 'compte sans adresse email',
            default            => null,
        };
        //dd($reason);
        if ($reason) {
            Log::info('Lien magique non envoyé', [
                'identifiant' => $data['email'] ?? $data['phone'] ?? null,
                'raison'      => $reason,
            ]);
        } else {
            $link = MagicLink::create([
                'user_id'    => $user->id,
                'token'      => Str::random(64),
                'channel'    => ($data['email'] ?? null) ? 'email' : 'sms',
                'expires_at' => now()->addMinutes(30),
            ]);
            //dd($link);
            if ($link->channel === 'email') {
                Notifier::send($user, new MagicLinkRequested($link));
            }
            // TODO SMS : brancher un fournisseur pour $link->channel === 'sms'
        }

        return back()->with('success', "Si un compte existe, un lien de connexion vient d'être envoyé.");
    }

    public function consume(Request $request, string $token)
    {
        $link = MagicLink::where('token', $token)->first();

        if (! $link || ! $link->isValid()) {
            return redirect()->route('client.login')->with('error', 'Lien expiré ou déjà utilisé.');
        }

        $link->update(['used_at' => now()]);

        Auth::login($link->user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('account');
    }
}
