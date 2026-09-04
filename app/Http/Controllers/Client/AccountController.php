<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Notifications\AccountBlocked;
use App\Notifications\AppointmentCancelledByClient;
use App\Notifications\CounterProposalAccepted;
use App\Services\BlockingService;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Espace client : statut des rendez-vous, carte de fidélité, dette. */
class AccountController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Client/Account', [
            'loyalty' => $user->tier,
            'debt'    => ['amount' => (float) $user->debt_amount, 'note' => $user->debt_note],

            'appointments' => Appointment::with(['service:id,name,includes', 'proposals'])
                ->where('user_id', $user->id)
                ->orderByDesc('starts_at')
                ->get()
                ->map(fn ($a) => [
                    'id'             => $a->id,
                    'service'        => $a->service->name,
                    'includes'       => $a->service->includes,
                    'starts_at'      => $a->starts_at->toIso8601String(),
                    'duration_min'   => $a->duration_min,
                    'status'         => $a->status,
                    'service_price'  => (float) $a->service_price,
                    'total_due'      => (float) $a->total_due,
                    'is_late_night'  => $a->is_late_night,
                    'is_cancellable' => $a->isCancellable(),
                    'admin_note'     => $a->admin_note,
                    'proposals'      => $a->proposals->map(fn ($p) => [
                        'id'        => $p->id,
                        'starts_at' => $p->starts_at->toIso8601String(),
                    ]),
                ]),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, BlockingService $blocking)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);

        if (! $appointment->isCancellable()) {
            return back()->with('error', "Le délai d'annulation est dépassé. Contactez le studio.");
        }

        $appointment->update([
            'status'       => Appointment::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => 'client',
        ]);

        Notifier::admins(new AppointmentCancelledByClient($appointment));

        // Blocage automatique si le seuil d'annulations est atteint
        if ($blocking->shouldAutoBlock($request->user())) {
            $blocking->block($request->user(), "Seuil d'annulations atteint");
            Notifier::send($request->user(), new AccountBlocked());
        }

        return back()->with('success', 'Rendez-vous annulé.');
    }

    /** Le client accepte l'un des créneaux alternatifs proposés. */
    public function acceptProposal(Request $request, AppointmentProposal $proposal)
    {
        $appointment = $proposal->appointment;

        abort_unless($appointment->user_id === $request->user()->id, 403);
        abort_unless($appointment->status === Appointment::COUNTER_PROPOSED, 409);

        $proposal->update(['is_accepted' => true]);

        $appointment->update([
            'starts_at'    => $proposal->starts_at,
            'ends_at'      => $proposal->ends_at,
            'status'       => Appointment::COUNTER_ACCEPTED,
            'confirmed_at' => now(),
        ]);

        Notifier::admins(new CounterProposalAccepted($appointment->fresh()));

        return back()->with('success', 'Nouveau créneau confirmé.');
    }

    public function refuseProposal(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);

        $appointment->update(['status' => Appointment::COUNTER_REFUSED]);

        return back()->with('success', 'Proposition refusée.');
    }
}
