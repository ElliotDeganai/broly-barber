<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\AvailabilityBreak;
use App\Models\AvailabilityException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Disponibilités. Ce que le barbier ouvre ici est la SEULE source du calendrier
 * client : rien n'est proposé qui ne soit déclaré ouvert.
 */
class AvailabilityController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Availability', [
            'weekly'     => Availability::orderBy('weekday')->orderBy('start_time')->get(),
            'breaks'     => AvailabilityBreak::orderBy('weekday')->orderBy('start_time')->get(),
            'exceptions' => AvailabilityException::orderBy('start_date')->get(),
        ]);
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
