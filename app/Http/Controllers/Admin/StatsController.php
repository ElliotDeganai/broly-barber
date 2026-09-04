<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\StatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Chiffre d'affaires : prestations réalisées et ventes de la boutique. */
class StatsController extends Controller
{
    /**
     * Périodes courantes, en un clic.
     *
     * Le barbier consulte presque toujours l'une de ces quatre fenêtres ;
     * saisir deux dates à chaque fois pour cela est une friction inutile.
     */
    private const PRESETS = ['month', 'quarter', 'year'];

    public function __invoke(Request $request, StatsService $stats)
    {
        $preset = $request->input('preset');
        [$from, $to] = $this->range($request, $preset);

        // Le regroupement s'adapte à la fenêtre : des jours sur un mois, des
        // mois sur une année. Sans cela, une année produit 365 barres illisibles.
        $granularity = $request->input('granularity') ?? match ($preset) {
            'month'   => 'day',
            'quarter' => 'week',
            default   => 'month',
        };

        $filters = array_filter(['service_id' => $request->integer('service_id') ?: null]);

        return Inertia::render('Admin/Stats', [
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'preset'      => $preset,
            'granularity' => $granularity,
            'filters'     => $filters,

            'revenue'    => $stats->revenue($from, $to, $filters),
            'volumes'    => $stats->volumes($from, $to),
            'series'     => $stats->series($from, $to, $granularity, $filters),
            'byService'  => $stats->byService($from, $to),
            'comparison' => $stats->comparison($from, $to, 1, $filters),

            'services' => Service::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(Request $request, ?string $preset): array
    {
        $today = CarbonImmutable::today();

        return match (true) {
            $preset === 'month'   => [$today->startOfMonth(), $today->endOfMonth()],
            $preset === 'quarter' => [$today->subMonths(2)->startOfMonth(), $today->endOfMonth()],
            $preset === 'year'    => [$today->startOfYear(), $today->endOfYear()],
            default => [
                CarbonImmutable::parse($request->input('from', $today->startOfYear()))->startOfDay(),
                CarbonImmutable::parse($request->input('to', $today))->endOfDay(),
            ],
        };
    }
}
