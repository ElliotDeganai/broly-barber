<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\AvailabilityBreak;
use App\Models\AvailabilityException;
use App\Models\SiteSetting;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Disponibilités. Ce que le barbier ouvre ici est la SEULE source du calendrier
 * client : rien n'est proposé qui ne soit déclaré ouvert.
 */
class AvailabilityController extends Controller
{
    /** Horizons proposés : la semaine, la quinzaine, le mois. */
    public const HORIZONS = [7, 14, 30];

    public function index()
    {
        $horizon = (int) SiteSetting::get('booking_horizon_days', 30);

        // Une valeur réglée autrement — par un ancien formulaire, par exemple —
        // doit rester choisie à l'affichage plutôt que de disparaître.
        $options = self::HORIZONS;

        if (! in_array($horizon, $options, true)) {
            $options[] = $horizon;
            sort($options);
        }

        return Inertia::render('Admin/Availability', [
            'weekly'     => Availability::orderBy('weekday')->orderBy('start_time')->get(),
            'breaks'     => AvailabilityBreak::orderBy('weekday')->orderBy('start_time')->get(),
            'exceptions' => AvailabilityException::orderBy('start_date')->get(),

            // Pas de la grille : il décide des heures de départ proposées
            'slotStep'    => AvailabilityService::step(),
            'stepOptions' => AvailabilityService::STEPS,

            // Horizon : jusqu'où le calendrier client est ouvert
            'horizonDays'    => $horizon,
            'horizonOptions' => $options,
        ]);
    }

    /**
     * Pas de la grille de réservation.
     *
     * Rangé ici plutôt que dans le contenu du site : c'est la forme du
     * planning, au même titre que les horaires et les pauses. Un seul écran
     * le modifie, pour qu'il n'y ait pas deux vérités.
     */
    public function saveSlotStep(Request $request)
    {
        $data = $request->validate([
            'step' => ['required', 'integer', Rule::in(AvailabilityService::STEPS)],
        ]);

        SiteSetting::updateOrCreate(
            ['key' => 'booking_slot_step'],
            [
                'value'     => (string) $data['step'],
                'type'      => 'number',
                'group'     => 'booking',
                'label'     => 'Pas des créneaux',
                'help'      => 'Réglé depuis les disponibilités.',
                'is_system' => true,
            ],
        );

        return back()->with('success', 'Pas des créneaux enregistré.');
    }

    /**
     * Jusqu'où les clients peuvent réserver.
     *
     * Bornes alignées sur ce que lit BookingController : en deçà de 7 jours le
     * calendrier n'aurait presque rien à montrer, au-delà de 60 il engagerait
     * le studio sur des horaires qui ne sont pas encore décidés.
     */
    public function saveHorizon(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:7', 'max:60'],
        ]);

        SiteSetting::put('booking_horizon_days', (string) $data['days']);

        return back()->with('success', 'Ouverture du calendrier enregistrée.');
    }

    public function saveWeekly(Request $request)
    {
        $data = $request->validate([
            'slots'               => ['present', 'array'],
            'slots.*.weekday'     => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'  => ['required', 'date_format:H:i'],
            'slots.*.end_time'    => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
            'slots.*.is_open'     => ['boolean'],
        ]);

        // Remplacement intégral : plus simple à raisonner qu'une synchronisation
        // ligne à ligne, et le volume est trivial (quelques dizaines de lignes).
        DB::transaction(function () use ($data) {
            Availability::query()->delete();

            foreach ($data['slots'] as $slot) {
                Availability::create($slot + ['is_open' => $slot['is_open'] ?? true]);
            }
        });

        return back()->with('success', 'Horaires enregistrés.');
    }

    public function saveBreaks(Request $request)
    {
        $data = $request->validate([
            'breaks'              => ['present', 'array'],
            'breaks.*.weekday'    => ['required', 'integer', 'between:0,6'],
            'breaks.*.start_time' => ['required', 'date_format:H:i'],
            'breaks.*.end_time'   => ['required', 'date_format:H:i', 'after:breaks.*.start_time'],
            'breaks.*.label'      => ['nullable', 'string', 'max:60'],
        ]);

        DB::transaction(function () use ($data) {
            AvailabilityBreak::query()->delete();

            foreach ($data['breaks'] as $break) {
                AvailabilityBreak::create($break);
            }
        });

        return back()->with('success', 'Pauses enregistrées.');
    }

    public function storeException(Request $request)
    {
        $data = $request->validate([
            'type'       => ['required', 'in:closed,open'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_if:type,open', 'date_format:H:i'],
            'end_time'   => ['nullable', 'required_if:type,open', 'date_format:H:i', 'after:start_time'],
            'label'      => ['nullable', 'string', 'max:60'],
        ]);

        AvailabilityException::create($data);

        return back()->with('success', 'Exception enregistrée.');
    }

    public function destroyException(AvailabilityException $exception)
    {
        $exception->delete();

        return back()->with('success', 'Exception supprimée.');
    }
}
