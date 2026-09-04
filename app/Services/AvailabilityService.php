<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Availability;
use App\Models\AvailabilityBreak;
use App\Models\AvailabilityException;
use App\Models\Service;
use App\Models\SiteSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Le calendrier client s'alimente UNIQUEMENT des disponibilités ouvertes par le
 * barbier.
 *
 * Ordre de résolution pour un jour donné :
 *   1. exception « closed » qui couvre la date  -> aucune plage
 *   2. exception « open »   qui couvre la date  -> la plage de l'exception
 *   3. sinon                                    -> l'horaire hebdomadaire
 * Puis on retranche les pauses, les rendez-vous existants et le délai minimum.
 */
class AvailabilityService
{
    /** Pas de la grille : un RDV de 45 min peut démarrer à 10:00, 10:15, 10:30... */
    public const GRID_STEP = 15;

    /** @return array<string, string[]>  ['2026-08-12' => ['10:00', '10:45'], ...] */
    public function slotsFor(Service $service, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to   = $to->endOfDay();

        $notBefore = CarbonImmutable::now()->addHours((int) SiteSetting::get('booking_lead_hours', 2));
        $busy      = $this->busyIntervals($from, $to);
        $out       = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $day) {
            $day   = CarbonImmutable::instance($day)->startOfDay();
            $slots = [];

            foreach ($this->openRangesFor($day) as [$start, $end]) {
                $cursor = $start;

                while ($cursor->addMinutes($service->duration_min)->lessThanOrEqualTo($end)) {
                    $slotEnd = $cursor->addMinutes($service->duration_min);

                    if ($cursor->greaterThanOrEqualTo($notBefore) && ! $this->overlaps($cursor, $slotEnd, $busy)) {
                        $slots[] = $cursor->format('H:i');
                    }

                    $cursor = $cursor->addMinutes(self::GRID_STEP);
                }
            }

            if ($slots) {
                $out[$day->toDateString()] = array_values(array_unique($slots));
            }
        }

        return $out;
    }

    /** Revérification serveur : le créneau demandé est-il réellement ouvert ? */
    public function isBookable(Service $service, CarbonImmutable $startsAt): bool
    {
        $day = $startsAt->startOfDay();

        return in_array(
            $startsAt->format('H:i'),
            $this->slotsFor($service, $day, $day)[$day->toDateString()] ?? [],
            true,
        );
    }

    /** @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    public function openRangesFor(CarbonImmutable $day): array
    {
        $exceptions = AvailabilityException::covering($day->toDateString())->get();

        if ($exceptions->firstWhere('type', 'closed')) {
            return [];
        }

        $ranges = [];

        if ($open = $exceptions->firstWhere('type', 'open')) {
            $ranges[] = [
                $day->setTimeFromTimeString($open->start_time),
                $day->setTimeFromTimeString($open->end_time),
            ];
        } else {
            foreach (Availability::where('weekday', $day->dayOfWeek)->where('is_open', true)->get() as $slot) {
                $ranges[] = [
                    $day->setTimeFromTimeString($slot->start_time),
                    $day->setTimeFromTimeString($slot->end_time),
                ];
            }
        }

        $breaks = AvailabilityBreak::where('weekday', $day->dayOfWeek)->get()
            ->map(fn ($b) => [
                $day->setTimeFromTimeString($b->start_time),
                $day->setTimeFromTimeString($b->end_time),
            ])->all();

        return $this->subtract($ranges, $breaks);
    }

    private function busyIntervals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Appointment::blocking()
            ->whereBetween('starts_at', [$from, $to])
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($a) => [
                CarbonImmutable::instance($a->starts_at),
                CarbonImmutable::instance($a->ends_at),
            ])->all();
    }

    private function overlaps(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as [$bStart, $bEnd]) {
            if ($start->lessThan($bEnd) && $end->greaterThan($bStart)) {
                return true;
            }
        }

        return false;
    }

    /** Retranche une liste d'intervalles à une liste de plages. */
    private function subtract(array $ranges, array $cuts): array
    {
        foreach ($cuts as [$cutStart, $cutEnd]) {
            $next = [];

            foreach ($ranges as [$start, $end]) {
                if ($cutEnd->lessThanOrEqualTo($start) || $cutStart->greaterThanOrEqualTo($end)) {
                    $next[] = [$start, $end];
                    continue;
                }
                if ($cutStart->greaterThan($start)) {
                    $next[] = [$start, $cutStart];
                }
                if ($cutEnd->lessThan($end)) {
                    $next[] = [$cutEnd, $end];
                }
            }

            $ranges = $next;
        }

        return $ranges;
    }
}
