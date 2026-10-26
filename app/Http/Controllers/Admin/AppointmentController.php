<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Notifications\AccountBlocked;
use App\Notifications\AppointmentCancelledByStudio;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentCounterProposed;
use App\Notifications\AppointmentRefused;
use App\Notifications\LoyaltyTierReached;
use App\Services\AvailabilityService;
use App\Services\BlockingService;
use App\Services\LoyaltyService;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

        // Trois échelles de lecture du planning. La date d'ancrage est la même
        // pour les trois : changer de vue ne fait pas perdre le jour regardé.
        $view = in_array($request->input('view'), ['day', 'week', 'month'], true)
            ? $request->input('view')
            : 'day';

        $anchor = CarbonImmutable::parse(
            // « month » reste accepté : d'anciens liens le portent encore.
            $request->input('date') ?? $request->input('month') ?? $focus?->starts_at ?? 'today',
        );

        /*
         * Statuts retenus. Par défaut, ce qui réclame encore quelque chose :
         * les annulations et les refus encombrent un planning qu'on ouvre pour
         * savoir ce qui est prévu.
         *
         * Exception : arrivée depuis un email, où le rendez-vous visé doit
         * apparaître quel que soit son statut — y compris une annulation,
         * puisque c'est justement l'objet du message.
         */
        $connus = [
            Appointment::PENDING, Appointment::COUNTER_PROPOSED,
            Appointment::CONFIRMED, Appointment::COUNTER_ACCEPTED,
            Appointment::COMPLETED, Appointment::REFUSED,
            Appointment::COUNTER_REFUSED, Appointment::CANCELLED, Appointment::NO_SHOW,
        ];

        $actifs = [
            Appointment::PENDING, Appointment::COUNTER_PROPOSED,
            Appointment::CONFIRMED, Appointment::COUNTER_ACCEPTED,
        ];

        $coches = array_filter((array) $request->input('status', []), 'is_string');

        /*
         * Absence totale du paramètre : premier affichage, on applique le
         * défaut. Une valeur présente mais inconnue — ce que la page envoie
         * quand tout est décoché — donne au contraire une liste vide, et donc
         * un planning vide. C'est ce que l'utilisateur a demandé.
         */
        $status = $coches === []
            ? ($focus ? $connus : $actifs)
            : array_values(array_intersect($coches, $connus));

        [$from, $to] = match ($view) {
            'day'   => [$anchor->startOfDay(), $anchor->endOfDay()],
            // Lundi explicite : la valeur par défaut de Carbon dépend de la
            // locale, et une semaine qui démarre le dimanche fausserait la grille.
            'week'  => [$anchor->startOfWeek(CarbonInterface::MONDAY), $anchor->endOfWeek(CarbonInterface::SUNDAY)],
            default => [$anchor->startOfMonth(), $anchor->endOfMonth()],
        };

        // Arrivée depuis un email : on filtre d'emblée sur le jour concerné,
        // sans quoi le barbier doit retrouver la demande dans tout le mois.
        if ($focus && ! $request->has('day')) {
            $request->merge(['day' => $focus->starts_at->toDateString()]);
        }

        /*
         * Légende : prestations actives, plus celles qui n'ont plus cours mais
         * apparaissent encore sur la période. Calculée AVANT la liste filtrée :
         * sinon filtrer sur une prestation ferait disparaître les autres de la
         * légende, et on ne pourrait plus les rallumer.
         */
        $legende = Service::where('is_active', true)
            ->orWhereIn('id', Appointment::whereBetween('starts_at', [$from, $to])->distinct()->pluck('service_id'))
            ->orderBy('position')
            ->get();

        $idsPrestations = $legende->pluck('id')->all();

        // Même logique que les statuts : paramètre absent, tout est retenu ;
        // présent mais sans correspondance, plus rien ne l'est.
        $cochesPrestations = array_filter((array) $request->input('service', []), 'is_scalar');

        $services = $cochesPrestations === []
            ? $idsPrestations
            : array_values(array_intersect(array_map('intval', $cochesPrestations), $idsPrestations));

        $appointments = Appointment::with(['user:id,name,phone,debt_amount', 'service:id,name,color', 'proposals'])
            ->whereBetween('starts_at', [$from, $to])
            ->whereIn('status', $status)
            ->whereIn('service_id', $services)
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
                'service_id'   => $a->service_id,
                'service_color' => $a->service->display_color,
                'starts_at'    => $a->starts_at->toIso8601String(),
                // Jour et minute d'ouverture dans le fuseau du studio :
                // les vues jour et semaine placent les blocs à partir de
                // ces deux valeurs, sans recalcul de fuseau côté client.
                'date'         => $a->starts_at->toDateString(),
                'start_min'    => $a->starts_at->hour * 60 + $a->starts_at->minute,
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
            ]);

        return Inertia::render('Admin/Appointments', [
            'view'   => $view,
            'date'   => $anchor->toDateString(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'status'        => $status,
            'statusAll'     => $connus,
            'statusDefault' => $actifs,
            'services'      => $services,
            'search' => $request->input('q'),
            'day'    => $request->input('day'),

            // Rendez-vous à mettre en avant, et son jour pour préfiltrer
            'focus' => $focus ? [
                'id'   => $focus->id,
                'date' => $focus->starts_at->toDateString(),
            ] : null,

            'appointments' => $appointments,

            // Charge par jour du mois : vue d'ensemble du planning
            'calendar' => $this->calendar($from, $to, $availability),

            'legend' => $legende
                ->map(fn (Service $s) => [
                    'id'    => $s->id,
                    'name'  => $s->name,
                    'color' => $s->display_color,
                ])
                ->values(),
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
    /**
     * Client absent.
     *
     * Statut distinct de l'annulation : dans l'historique du client comme
     * dans les statistiques, « pas venu » et « a prévenu » ne racontent pas
     * la même chose. L'absence alimente en revanche le même compteur que les
     * annulations tardives — le créneau est perdu dans les deux cas.
     */
    public function noShow(Appointment $appointment, BlockingService $blocking)
    {
        if (! in_array($appointment->status, [Appointment::CONFIRMED, Appointment::COUNTER_ACCEPTED], true)) {
            return back()->with('error', "Seul un rendez-vous confirmé peut être marqué comme non honoré.");
        }

        $appointment->forceFill([
            'status'       => Appointment::NO_SHOW,
            'cancelled_at' => now(),
            'cancelled_by' => 'client',
        ])->save();

        if ($blocking->shouldAutoBlock($appointment->user)) {
            $blocking->block($appointment->user, 'Absences et annulations répétées');
            Notifier::send($appointment->user, new AccountBlocked());

            return back()->with('success', "Absence enregistrée. Le seuil est atteint : l'accès à la réservation est suspendu.");
        }

        return back()->with('success', 'Absence enregistrée.');
    }

    /**
     * Annulation par le studio.
     *
     * `cancelled_by` vaut « admin » : cette annulation ne doit pas compter
     * contre le client, qui n'y est pour rien.
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $ouverts = [
            Appointment::PENDING, Appointment::CONFIRMED,
            Appointment::COUNTER_PROPOSED, Appointment::COUNTER_ACCEPTED,
        ];

        if (! in_array($appointment->status, $ouverts, true)) {
            return back()->with('error', 'Ce rendez-vous est déjà clos.');
        }

        $appointment->status       = Appointment::CANCELLED;
        $appointment->cancelled_at = now();
        $appointment->cancelled_by = 'admin';

        // Une annulation sans motif ne doit pas effacer une note déjà saisie.
        if (filled($data['reason'] ?? null)) {
            $appointment->admin_note = $data['reason'];
        }

        $appointment->save();

        Notifier::send($appointment->user, new AppointmentCancelledByStudio($appointment));

        return back()->with('success', 'Rendez-vous annulé, le client est prévenu.');
    }

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
    private function calendar(CarbonImmutable $from, CarbonImmutable $to, AvailabilityService $availability): array
    {
        $booked = Appointment::blocking()
            ->whereBetween('starts_at', [$from, $to])
            ->get(['starts_at', 'duration_min'])
            ->groupBy(fn ($a) => $a->starts_at->toDateString())
            ->map(fn ($day) => $day->sum('duration_min'));

        $step = AvailabilityService::step();
        $days = [];

        for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $ranges = $availability->openRangesFor($date);

            $open = collect($ranges)->sum(fn ($r) => $r[0]->diffInMinutes($r[1]));
            $used = (int) ($booked[$date->toDateString()] ?? 0);

            // Créneaux de la grille de réservation. Le rapport affiché est
            // exactement celui de la jauge : le chiffre explique la barre au
            // lieu de raconter autre chose.
            $total = intdiv($open, $step);

            $days[] = [
                'date'        => $date->toDateString(),
                'open_min'    => $open,
                'used_min'    => $used,
                'load'        => $open > 0 ? (int) round($used / $open * 100) : 0,
                'slots_total' => $total,
                'slots_used'  => min($total, (int) ceil($used / $step)),
                'ranges'      => array_map(
                    function ($r) {
                        $fin = $r[1]->hour * 60 + $r[1]->minute;

                        return [
                            $r[0]->hour * 60 + $r[0]->minute,
                            // Une plage qui se ferme à minuit retombe à 0 :
                            // sans ce rattrapage elle serait dessinée à l'envers.
                            $fin > 0 ? $fin : 1440,
                        ];
                    },
                    $ranges,
                ),
            ];
        }

        return $days;
    }
}
