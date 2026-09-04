<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\AvailabilityService;
use App\Notifications\AppointmentRequested;
use App\Services\PricingService;
use App\Support\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Tunnel de réservation : prestation → créneau → confirmation.
 *
 * Le prix et la disponibilité sont TOUJOURS recalculés côté serveur à la
 * validation : ce qui vient du formulaire n'est pas une source de confiance.
 */
class BookingController extends Controller
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
    ) {}

    /** Étape 1 — choix de la prestation, avec ce qui est compris. */
    public function services()
    {
        return Inertia::render('Booking/Services', [
            'services' => Service::active()->get([
                'id', 'name', 'slug', 'includes', 'description',
                'has_restructuration', 'price', 'duration_min', 'image_path',
            ]),
            'lateOffer' => [
                'hour'       => (int) SiteSetting::get('late_night_hour', 20),
                'multiplier' => (float) SiteSetting::get('late_night_multiplier', 2),
            ],
        ]);
    }

    /** Étape 2 — créneaux réellement ouverts pour cette prestation. */
    public function slots(Request $request, Service $service)
    {
        abort_unless($service->is_active, 404);

        $from = CarbonImmutable::parse($request->input('from', 'today'));
        $days = min(60, max(7, (int) SiteSetting::get('booking_horizon_days', 30)));

        return Inertia::render('Booking/Slots', [
            'service' => $service->only(['id', 'name', 'slug', 'includes', 'duration_min', 'price']),
            'from'    => $from->toDateString(),
            'slots'   => $this->availability->slotsFor($service, $from, $from->addDays($days)),
            'quote'   => $this->pricing->quote($request->user(), $service),
        ]);
    }

    /** Étape 3 — récapitulatif et conditions. */
    public function confirm(Request $request, Service $service)
    {
        $data = $request->validate(['starts_at' => ['required', 'date']]);
        $startsAt = CarbonImmutable::parse($data['starts_at']);

        return Inertia::render('Booking/Confirm', [
            'service'  => $service->only(['id', 'name', 'slug', 'includes', 'description', 'duration_min']),
            'startsAt' => $startsAt->toIso8601String(),
            'quote'    => $this->pricing->quote($request->user(), $service, $startsAt),
            'terms'    => [
                'cancellation_hours' => (int) SiteSetting::get('cancellation_hours', 12),
                'response_hours'     => (int) SiteSetting::get('response_hours', 24),
            ],
        ]);
    }

    /** Envoi de la demande — statut « pending », validation manuelle du barbier. */
    public function store(Request $request, Service $service)
    {
        $data = $request->validate([
            'starts_at'      => ['required', 'date', 'after:now'],
            'comment'        => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['accepted'],
        ]);

        $user     = $request->user();
        $startsAt = CarbonImmutable::parse($data['starts_at']);

        if (! $this->availability->isBookable($service, $startsAt)) {
            return back()->with('error', "Ce créneau vient d'être pris. Choisissez-en un autre.");
        }

        $quote = $this->pricing->quote($user, $service, $startsAt);

        $appointment = DB::transaction(fn () => Appointment::create([
            'user_id'           => $user->id,
            'service_id'        => $service->id,
            'starts_at'         => $startsAt,
            'ends_at'           => $startsAt->addMinutes($service->duration_min),
            'duration_min'      => $service->duration_min,
            'status'            => Appointment::PENDING,
            'service_price'     => $quote['service_price'],
            'is_late_night'     => $quote['is_late_night'],
            'debt_snapshot'     => $quote['debt'],
            'total_due'         => $quote['total_due'],
            'client_comment'    => $data['comment'] ?? null,
            'terms_accepted_at' => now(),
            'terms_accepted_ip' => $request->ip(),
        ]));

        Notifier::admins(new AppointmentRequested($appointment));

        $hours = (int) SiteSetting::get('response_hours', 24);

        return redirect()->route('account')
            ->with('success', "Demande envoyée. Le barbier vous répond sous {$hours} h.");
    }
}
