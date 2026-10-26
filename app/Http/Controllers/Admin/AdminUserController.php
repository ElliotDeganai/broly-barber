<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminAccessGranted;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Comptes administrateurs.
 *
 * Un administrateur n'est pas un client avec une case cochée : il entre par
 * email et mot de passe là où le client entre par lien magique, et il reçoit
 * les notifications du studio (Notifier::admins). D'où une page à part.
 */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $moi = $request->user()->id;

        return Inertia::render('Admin/Admins', [
            'admins' => User::where('role', 'admin')
                ->orderBy('name')
                ->get()
                ->map(fn (User $a) => [
                    'id'      => $a->id,
                    'name'    => $a->name,
                    'email'   => $a->email,
                    'phone'   => $a->phone,
                    'since'   => $a->created_at?->locale('fr_FR')->isoFormat('D MMMM YYYY'),
                    'is_self' => $a->id === $moi,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone'    => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'notify'   => ['boolean'],
        ], [
            'email.unique' => "Un compte existe déjà avec cette adresse. Utilisez « Promouvoir un compte existant ».",
        ]);

        $admin = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            // Le cast « hashed » du modèle chiffre à l'enregistrement.
            'password' => $data['password'],
            'role'     => 'admin',
            // Aucun parcours de vérification n'existe pour un compte créé
            // par le studio : le laisser à null bloquerait un éventuel
            // middleware « verified » ajouté plus tard.
            'email_verified_at' => now(),
        ]);

        if ($data['notify'] ?? true) {
            Notifier::send($admin, new AdminAccessGranted());
        }

        return back()->with('success', "Compte administrateur créé pour {$admin->name}.");
    }

    /**
     * Promotion d'un compte déjà existant.
     *
     * Le cas courant : le barbier s'est d'abord créé un compte client. Mieux
     * vaut lui donner les droits que créer un doublon qui couperait son
     * historique de rendez-vous en deux.
     */
    public function promote(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email', Rule::exists('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'notify'   => ['boolean'],
        ], [
            'email.exists' => "Aucun compte avec cette adresse. Utilisez le formulaire de création.",
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        if ($user->is_admin) {
            return back()->withErrors(['email' => 'Ce compte est déjà administrateur.']);
        }

        // Un compte client créé au studio n'a qu'un mot de passe aléatoire :
        // sans en fixer un ici, le nouvel administrateur ne pourrait pas se
        // connecter au back-office.
        $user->forceFill([
            'role'              => 'admin',
            'password'          => $data['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
            // Les droits admin et le blocage de la réservation ne vont pas
            // ensemble : un compte bloqué resterait sans accès client.
            'is_blocked'        => false,
            'block_reason'      => null,
            'blocked_at'        => null,
        ])->save();

        if ($data['notify'] ?? true) {
            Notifier::send($user, new AdminAccessGranted());
        }

        return back()->with('success', "{$user->name} est maintenant administrateur.");
    }

    /**
     * Retrait des droits : le compte redevient client, il n'est pas supprimé.
     *
     * Supprimer effacerait l'historique de rendez-vous et de ventes rattaché
     * à cette personne, ce qui fausserait les statistiques.
     */
    public function demote(Request $request, User $admin)
    {
        if ($admin->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas retirer vos propres droits.');
        }

        if (! $admin->is_admin) {
            return back()->with('error', "Ce compte n'est pas administrateur.");
        }

        // Garde-fou : plus aucun administrateur et le back-office devient
        // inaccessible autrement qu'en base de données.
        if (User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Il doit rester au moins un administrateur.');
        }

        $admin->forceFill(['role' => 'client'])->save();

        return back()->with('success', "{$admin->name} n'est plus administrateur.");
    }
}
