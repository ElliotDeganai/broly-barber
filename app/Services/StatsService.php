<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ProductSale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** CA prestations (RDV réalisés) + CA boutique (ventes saisies au studio). */
class StatsService
{
    public function revenue(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $services = Appointment::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->when($filters['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->sum('service_price');

        $products = ProductSale::whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->sum('total_ttc');

        return [
            'services' => round((float) $services, 2),
            'products' => round((float) $products, 2),
            'total'    => round((float) $services + (float) $products, 2),
        ];
    }

    /** $granularity : day | week | month | year */
    public function series(CarbonImmutable $from, CarbonImmutable $to, string $granularity, array $filters = []): array
    {
        $format = match ($granularity) {
            'day'   => '%Y-%m-%d',
            'week'  => '%x-W%v',
            'year'  => '%Y',
            default => '%Y-%m',
        };

        $services = Appointment::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->when($filters['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))
            ->select(DB::raw("DATE_FORMAT(completed_at, '{$format}') as bucket"), DB::raw('SUM(service_price) as amount'))
            ->groupBy('bucket')->pluck('amount', 'bucket');

        $products = ProductSale::whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->select(DB::raw("DATE_FORMAT(sold_at, '{$format}') as bucket"), DB::raw('SUM(total_ttc) as amount'))
            ->groupBy('bucket')->pluck('amount', 'bucket');

        return collect($services->keys())->merge($products->keys())->unique()->sort()->values()
            ->map(fn ($b) => [
                'bucket'   => $b,
                'services' => round((float) ($services[$b] ?? 0), 2),
                'products' => round((float) ($products[$b] ?? 0), 2),
                'total'    => round((float) ($services[$b] ?? 0) + (float) ($products[$b] ?? 0), 2),
            ])->all();
    }

    /**
     * Répartition du chiffre d'affaires par prestation.
     *
     * Répond à la question de gestion la plus utile au barbier : quelle coupe
     * fait vivre le studio. Triée par montant décroissant.
     */
    public function byService(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Appointment::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->select(
                'services.name',
                DB::raw('SUM(appointments.service_price) as amount'),
                DB::raw('COUNT(*) as count'),
            )
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'name'   => $row->name,
                'amount' => round((float) $row->amount, 2),
                'count'  => (int) $row->count,
            ])
            ->all();
    }

    /** Volumes, pour donner un ordre de grandeur aux montants. */
    public function volumes(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appointments = Appointment::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $products = (int) ProductSale::whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])
            ->sum('quantity');

        return [
            'appointments' => $appointments,
            'products'     => $products,
        ];
    }

    /** Comparaison N vs N-1, avec N-2 en option. */
    public function comparison(CarbonImmutable $from, CarbonImmutable $to, int $backYears = 1, array $filters = []): array
    {
        $out = ['n' => $this->revenue($from, $to, $filters)];

        for ($i = 1; $i <= $backYears; $i++) {
            $out["n-{$i}"] = $this->revenue($from->subYears($i), $to->subYears($i), $filters);
        }

        return $out;
    }
}
