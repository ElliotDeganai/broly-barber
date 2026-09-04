<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ProductSale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;

/** Tableau de bord : ce que le barbier doit voir en ouvrant l'application. */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = CarbonImmutable::today();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'pending'        => Appointment::where('status', Appointment::PENDING)->count(),
                'today'          => Appointment::whereDate('starts_at', $today)->blocking()->count(),
                'clients'        => User::clients()->count(),
                'blocked'        => User::clients()->blocked()->count(),
                'in_debt'        => User::clients()->inDebt()->count(),
                'revenue_month'  => round(
                    (float) Appointment::completed()->whereBetween('completed_at', [$today->startOfMonth(), $today->endOfMonth()])->sum('service_price')
                    + (float) ProductSale::whereBetween('sold_at', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])->sum('total_ttc'),
                    2,
                ),
            ],

            // Journée en cours, dans l'ordre horaire
            'todayAppointments' => Appointment::with(['user:id,name', 'service:id,name'])
                ->whereDate('starts_at', $today)
                ->orderBy('starts_at')
                ->get()
                ->map(fn ($a) => $this->row($a)),

            // Demandes en attente : l'action principale du barbier
            'pendingAppointments' => Appointment::with(['user:id,name,debt_amount', 'service:id,name'])
                ->where('status', Appointment::PENDING)
                ->orderBy('starts_at')
                ->limit(10)
                ->get()
                ->map(fn ($a) => $this->row($a) + ['debt' => (float) $a->user->debt_amount]),
        ]);
    }

    private function row(Appointment $a): array
    {
        return [
            'id'           => $a->id,
            'client'       => $a->user->name,
            'service'      => $a->service->name,
            'starts_at'    => $a->starts_at->toIso8601String(),
            'duration_min' => $a->duration_min,
            'status'       => $a->status,
            'total_due'    => (float) $a->total_due,
        ];
    }
}
