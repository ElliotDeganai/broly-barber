<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\SiteSetting;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentCounterProposed;
use App\Notifications\AppointmentRefused;
use App\Notifications\LoyaltyTierReached;
use App\Services\AvailabilityService;
use App\Services\LoyaltyService;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Gestion des rendez-vous : validation manuelle, refus, contre-proposition de
 * 1 à 3 créneaux, passage en réalisé.
 */
class AppointmentController extends Controller
{
    public function index(Request $request, AvailabilityService $availability)
    {
        // Lien reçu par email : on se cale sur le mois du rendez-vous concerné
        // plutôt que sur le mois courant, sinon le barbier arrive sur un
        // planning où la demande n'apparaît pas.
        $focus = $request->integer('appointment')
            ? Appointment::find($request->integer('appointment'))
            : null;

        $month = CarbonImmutable::parse(
            $request->input('month') ?? $focus?->starts_at ?? 'first day of this month',
        )->startOfMonth();

        // Filet de sécurité pour les liens émis avant que le filtre ne soit
        // porté par l'URL : sans jour précisé, on se cale sur celui du
        // rendez-vous ciblé.
        if ($focus && ! $request->filled('day')) {
            $request->merge(['day' => $focus->starts_at->toDateString()]);
        }

        return Inertia::render('Admin/Appointments', [
            'month'  => $month->toDateString(),
            'status' => $request->input('status'),
            'search' => $request->input('q'),
            'day'    => $request->input('day'),

            // Rendez-vous à mettre en avant, et son jour pour préfiltrer
            'focus' => $focus ? [
                'id'   => $focus->id,
                'date' => $focus->starts_at->toDateString(),
            ] : null,

            'appointments' => Appointment::with(['user:id,name,phone,debt_amount', 'service:id,name', 'proposals'])
                ->whereBetween('starts_at', [$month, $month->endOfMonth()])
                ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
                // Recherche sur le client : nom, email ou téléphone
                ->when($request->input('q'), fn ($q, $term) => $q->whereHas(
                    'user',
                    fn ($sub) => $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%"),
                ))
                // Filtre par jour appliqué côté serveur : la liste reste juste
                // même si le mois compte des centaines de rendez-vous.
                ->when($request->input('day'), fn ($q, $day) => $q->whereDate('starts_at', $day))
                ->orderBy('starts_at')
                ->get()
                ->map(fn ($a) => [
                    'id'           => $a->id,
                    'client'       => $a->user->name,
                    'phone'        => $a->user->phone,
                    'debt'         => (float) $a->user->debt_amount,
                    'service'      => $a->service->name,
                    'starts_at'    => $a->starts_at->toIso8601String(),
                    'duration_min' => $a->duration_min,
                    'status'       => $a->status,
                    'service_price'=> (float) $a->service_price,
                    'total_due'    => (float) $a->total_due,
                    'is_late_night'=> $a->is_late_night,
                    'comment'      => $a->client_comment,
                    'admin_note'   => $a->admin_note,
                    'proposals'    => $a->proposals->map(fn ($p) => [
                        'id'        => $p->id,
                        'starts_at' => $p->starts_at->toIso8601String(),
                    ]),
                ]),

            // Charge par jour du mois : vue d'ensemble du planning
            'calendar' => $this->calendar($month, $availability),
        ]);
    }

    /**
     * Créneaux libres pour la prestation de ce rendez-vous.
     *
     * Servis en JSON plutôt qu'intégrés à la page : la liste dépend du
     * rendez-vous ouvert, et la calculer pour tous ceux du mois coûterait
     * autant de parcours du planning qu'il y a de lignes.
     */
    public function availableSlots(Appointment $appointment, AvailabilityService $availability)
    {
        $from = CarbonImmutable::today();

        return response()->json([
            'duration_min' => $appointment->duration_min,
            'slots'        => $availability->slotsFor(
                $appointment->service,
                $from,
                $from->addDays((int) SiteSetting::get('booking_horizon_days', 30)),
            ),
        ]);
    }

    public function confirm(Appointment $appointment)
    {
        abort_unless($appointment->status === Appointment::PENDING, 409);

        $appointment->update([
            'status'       => Appointment::CONFIRMED,
            'confirmed_at' => now(),
        ]);

        Notifier::send($appointment->user, new AppointmentConfirmed($appointment));

        return back()->with('success', 'Rendez-vous confirmé.');
    }

    public function refuse(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $appointment->update([
            'status'     => Appointment::REFUSED,
            'admin_note' => $data['reason'] ?? null,
        ]);

        Notifier::send($appointment->user, new AppointmentRefused($appointment));

        return back()->with('success', 'Rendez-vous refusé.');
    }

    /** Contre-proposition : de 1 à 3 créneaux alternatifs soumis au client. */
    public function counterPropose(Request $request, Appointment $appointment, AvailabilityService $availability)
    {
        $data = $request->validate([
            'slots'   => ['required', 'array', 'min:1', 'max:3'],
            'slots.*' => ['required', 'date', 'after:now'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        // Revérification : le formulaire n'est pas une source de confiance, et
        // un créneau a pu être pris entre l'ouverture de l'écran et l'envoi.
        foreach ($data['slots'] as $slot) {
            if (! $availability->isBookable($appointment->service, CarbonImmutable::parse($slot))) {
                return back()->with(
                    'error',
                    'Un des créneaux proposés n\'est plus disponible. Rechargez la page.',
                );
            }
        }

        DB::transaction(function () use ($appointment, $data) {
            $appointment->proposals()->delete();

            foreach ($data['slots'] as $slot) {
                $starts = CarbonImmutable::parse($slot);

                $appointment->proposals()->create([
                    'starts_at' => $starts,
                    'ends_at'   => $starts->addMinutes($appointment->duration_min),
                ]);
            }

            $appointment->update([
                'status'     => Appointment::COUNTER_PROPOSED,
                'admin_note' => $data['note'] ?? null,
            ]);
        });

        Notifier::send($appointment->user, new AppointmentCounterProposed($appointment->fresh('proposals')));

        return back()->with('success', 'Contre-proposition envoyée.');
    }

    /** Passage en réalisé : c'est ce qui incrémente le compteur de fidélité. */
    public function complete(Appointment $appointment, LoyaltyService $loyalty)
    {
        $appointment->update([
            'status'       => Appointment::COMPLETED,
            'completed_at' => now(),
        ]);

        $before = $appointment->user->tier['key'];
        $loyalty->recount($appointment->user);
        $after = $appointment->user->fresh()->tier;

        // Une notification n'est envoyée qu'au franchissement, pas à chaque visite
        if ($after['key'] !== $before) {
            Notifier::send($appointment->user, new LoyaltyTierReached($after));
        }

        return back()->with('success', 'Rendez-vous marqué comme réalisé.');
    }

    /** Taux d'occupation par jour, pour colorer le calendrier du mois. */
    private function calendar(CarbonImmutable $month, AvailabilityService $availability): array
    {
        $booked = Appointment::blocking()
            ->whereBetween('starts_at', [$month, $month->endOfMonth()])
            ->get(['starts_at', 'duration_min'])
            ->groupBy(fn ($a) => $a->starts_at->toDateString())
            ->map(fn ($day) => $day->sum('duration_min'));

        $days = [];

        for ($date = $month; $date->lessThanOrEqualTo($month->endOfMonth()); $date = $date->addDay()) {
            $open = collect($availability->openRangesFor($date))
                ->sum(fn ($r) => $r[0]->diffInMinutes($r[1]));

            $used = (int) ($booked[$date->toDateString()] ?? 0);

            $days[] = [
                'date'      => $date->toDateString(),
                'open_min'  => $open,
                'used_min'  => $used,
                'load'      => $open > 0 ? (int) round($used / $open * 100) : 0,
            ];
        }

        return $days;
    }
}
