<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\User;
use App\Notifications\AccountBlocked;
use App\Notifications\MagicLinkRequested;
use App\Services\BlockingService;
use App\Support\Notifier;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Clients : création au studio, dettes, blocage, fidélité. */
class ClientController extends Controller
{
    public function index(Request $request, LoyaltyService $loyalty)
    {
        return Inertia::render('Admin/Clients', [
            'filter' => $request->input('filter'),
            'search' => $request->input('q'),

            'clients' => User::clients()
                ->when($request->input('q'), fn ($q, $term) => $q->where(
                    fn ($sub) => $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"),
                ))
                ->when($request->input('filter') === 'blocked', fn ($q) => $q->blocked())
                ->when($request->input('filter') === 'debt', fn ($q) => $q->inDebt())
                ->orderBy('name')
                ->paginate(30)
                ->through(fn ($c) => [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'email'        => $c->email,
                    'phone'        => $c->phone,
                    'is_offline'   => $c->is_offline,
                    'is_blocked'   => $c->is_blocked,
                    'block_reason' => $c->block_reason,
                    'debt_amount'  => (float) $c->debt_amount,
                    'debt_note'    => $c->debt_note,
                    'visits_count' => $c->visits_count,
                    'tier'         => $loyalty->tierFor($c->visits_count),
                ]),

            'tiers' => LoyaltyService::TIERS,
        ]);
    }

    /** Client créé au studio : il n'a pas de mot de passe, il entre par lien magique. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        User::create($data + [
            'password'   => Str::random(40),
            'role'       => 'client',
            'is_offline' => true,
        ]);

        return back()->with('success', 'Client créé.');
    }

    /** Règlement total ou partiel d'une dette, ou ajout d'un nouveau montant. */
    public function updateDebt(Request $request, User $client)
    {
        $data = $request->validate([
            'mode'   => ['required', 'in:set,add,settle'],
            'amount' => ['required_unless:mode,settle', 'nullable', 'numeric', 'min:0'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $amount = match ($data['mode']) {
            'set'    => (float) $data['amount'],
            'add'    => (float) $client->debt_amount + (float) $data['amount'],
            'settle' => max(0, (float) $client->debt_amount - (float) ($data['amount'] ?? 0)),
        };

        $client->forceFill([
            'debt_amount' => round($amount, 2),
            // Une dette soldée n'a plus de motif à afficher
            'debt_note'   => $amount > 0 ? ($data['note'] ?? $client->debt_note) : null,
        ])->save();

        return back()->with('success', 'Dette mise à jour.');
    }

    public function toggleBlock(Request $request, User $client, BlockingService $blocking)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        if ($client->is_blocked) {
            $blocking->unblock($client);
        } else {
            $blocking->block($client, $data['reason'] ?? null);
            // Le client doit savoir pourquoi il ne peut plus réserver ; le motif
            // interne, lui, reste au studio.
            Notifier::send($client, new AccountBlocked());
        }

        return back()->with('success', $client->is_blocked ? 'Client débloqué.' : 'Client bloqué.');
    }

    /** Envoi manuel d'un accès, utile pour un client créé au studio. */
    public function sendMagicLink(User $client)
    {
        if (! $client->email) {
            return back()->with('error', "Ce client n'a pas d'adresse email.");
        }

        $link = MagicLink::create([
            'user_id'    => $client->id,
            'token'      => Str::random(64),
            'channel'    => 'email',
            'expires_at' => now()->addMinutes(30),
        ]);

        Notifier::send($client, new MagicLinkRequested($link));

        return back()->with('success', 'Lien de connexion envoyé.');
    }
}
