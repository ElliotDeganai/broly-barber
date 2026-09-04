<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\User;
use App\Services\LoyaltyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu d'essai.
 *
 * ⚠ Environnement local UNIQUEMENT. Le seeder refuse de s'exécuter en
 * production : des clients fictifs dans la vraie base fausseraient le chiffre
 * d'affaires et la fidélité.
 *
 * Il crée des clients couvrant chacun un cas de figure du parcours utilisateur,
 * pour que chaque écran du back office ait quelque chose à montrer.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DemoDataSeeder est réservé au développement.');

            return;
        }

        $services = Service::active()->get();

        if ($services->isEmpty()) {
            $this->command->error('Aucune prestation : lancez ServiceSeeder avant.');

            return;
        }

        $clients = $this->clients();
        $this->appointments($clients, $services);
        $this->sales($clients);

        // Le compteur de visites est dénormalisé : il se recalcule après coup
        $loyalty = app(LoyaltyService::class);
        foreach ($clients as $client) {
            $loyalty->recount($client);
        }

        $this->command->info('Jeu d\'essai créé. Mot de passe commun : password');
        $this->command->table(
            ['Email', 'Cas de figure'],
            [
                ['diamant@test.local',  'Fidèle, palier Diamant, historique long'],
                ['or@test.local',       'Palier Or, dette de 25 € en cours'],
                ['argent@test.local',   'Palier Argent, contre-proposition en attente'],
                ['bronze@test.local',   'Nouveau client, une demande en attente'],
                ['bloque@test.local',   'Client bloqué, ne peut plus réserver'],
                ['studio@test.local',   'Créé au studio, sans mot de passe (lien magique)'],
            ],
        );
    }

    /** @return array<string, User> */
    private function clients(): array
    {
        $profiles = [
            'diamant' => ['name' => 'Kenji Tanaka',    'visits' => 68, 'debt' => 0],
            'or'      => ['name' => 'Marc Dubois',     'visits' => 31, 'debt' => 25],
            'argent'  => ['name' => 'Sofiane Amrani',  'visits' => 15, 'debt' => 0],
            'bronze'  => ['name' => 'Thomas Leroy',    'visits' => 3,  'debt' => 0],
            'bloque'  => ['name' => 'Client Bloqué',   'visits' => 8,  'debt' => 45],
            'studio'  => ['name' => 'Yuki Nakamura',   'visits' => 0,  'debt' => 0],
        ];

        $clients = [];

        foreach ($profiles as $key => $profile) {
            $clients[$key] = User::updateOrCreate(
                ['email' => "{$key}@test.local"],
                [
                    'name'              => $profile['name'],
                    'password'          => Hash::make('password'),
                    'role'              => 'client',
                    'phone'             => '06 00 00 00 0' . array_search($key, array_keys($profiles)),
                    'email_verified_at' => now(),
                    'debt_amount'       => $profile['debt'],
                    'debt_note'         => $profile['debt'] > 0 ? 'Reliquat du dernier passage' : null,
                    // Créé au studio : pas de mot de passe utilisable, accès par lien
                    'is_offline'        => $key === 'studio',
                    'is_blocked'        => $key === 'bloque',
                    'block_reason'      => $key === 'bloque' ? 'Trois annulations tardives' : null,
                    'blocked_at'        => $key === 'bloque' ? now()->subWeeks(2) : null,
                ],
            );
        }

        return $clients;
    }

    private function appointments(array $clients, $services): void
    {
        Appointment::whereIn('user_id', collect($clients)->pluck('id'))->delete();

        // --- Historique réalisé : alimente fidélité et chiffre d'affaires ---
        $history = ['diamant' => 68, 'or' => 31, 'argent' => 15, 'bronze' => 3, 'bloque' => 8];

        foreach ($history as $key => $count) {
            for ($i = 0; $i < $count; $i++) {
                $service = $services->random();

                // Réparti sur 24 mois pour que les statistiques aient du relief
                $date = CarbonImmutable::now()
                    ->subDays(random_int(3, 720))
                    ->setTime(random_int(10, 19), [0, 15, 30, 45][random_int(0, 3)]);

                $this->make($clients[$key], $service, $date, Appointment::COMPLETED);
            }
        }

        // --- Demandes en attente : l'action principale du barbier ---
        $this->make($clients['bronze'], $services->first(),
            $this->nextOpenDay(2)->setTime(14, 30), Appointment::PENDING);

        $this->make($clients['diamant'], $services->random(),
            $this->nextOpenDay(4)->setTime(11, 0), Appointment::PENDING);

        // Créneau tardif : vérifie le multiplicateur après 20 h
        $this->make($clients['or'], $services->random(),
            $this->nextOpenDay(3)->setTime(20, 30), Appointment::PENDING);

        // --- Rendez-vous confirmés, dont un aujourd'hui ---
        $this->make($clients['or'], $services->random(),
            CarbonImmutable::today()->setTime(16, 0), Appointment::CONFIRMED);

        $this->make($clients['diamant'], $services->random(),
            $this->nextOpenDay(6)->setTime(15, 0), Appointment::CONFIRMED);

        // --- Contre-proposition en attente de réponse du client ---
        $counter = $this->make($clients['argent'], $services->random(),
            $this->nextOpenDay(5)->setTime(18, 0), Appointment::COUNTER_PROPOSED);

        foreach ([1, 2, 3] as $offset) {
            $start = $this->nextOpenDay(5 + $offset)->setTime(10 + $offset, 0);

            AppointmentProposal::create([
                'appointment_id' => $counter->id,
                'starts_at'      => $start,
                'ends_at'        => $start->addMinutes($counter->duration_min),
            ]);
        }

        // --- Annulations : nourrissent le seuil de blocage automatique ---
        foreach ([10, 24, 38] as $daysAgo) {
            $appointment = $this->make(
                $clients['bloque'], $services->random(),
                CarbonImmutable::now()->subDays($daysAgo)->setTime(14, 0),
                Appointment::CANCELLED,
            );

            $appointment->update([
                'cancelled_at' => CarbonImmutable::now()->subDays($daysAgo + 1),
                'cancelled_by' => 'client',
            ]);
        }

        // --- Un refus, pour voir le statut correspondant ---
        $this->make($clients['bronze'], $services->random(),
            CarbonImmutable::now()->subDays(15)->setTime(12, 0), Appointment::REFUSED);
    }

    private function make(User $user, Service $service, CarbonImmutable $startsAt, string $status): Appointment
    {
        // Le tarif est figé à la création, comme en production
        $isLate = $startsAt->hour >= 20;
        $price  = (float) $service->price * ($isLate ? 2 : 1);

        return Appointment::create([
            'user_id'           => $user->id,
            'service_id'        => $service->id,
            'starts_at'         => $startsAt,
            'ends_at'           => $startsAt->addMinutes($service->duration_min),
            'duration_min'      => $service->duration_min,
            'status'            => $status,
            'service_price'     => $price,
            'is_late_night'     => $isLate,
            'debt_snapshot'     => 0,
            'total_due'         => $price,
            'terms_accepted_at' => $startsAt->subDays(1),
            'terms_accepted_ip' => '127.0.0.1',
            'confirmed_at'      => in_array($status, [Appointment::CONFIRMED, Appointment::COMPLETED], true)
                ? $startsAt->subDays(1) : null,
            'completed_at'      => $status === Appointment::COMPLETED ? $startsAt : null,
        ]);
    }

    private function sales(array $clients): void
    {
        $products = Product::published()->get();

        if ($products->isEmpty()) {
            return;
        }

        ProductSale::truncate();

        // 60 ventes sur 18 mois : de quoi remplir les graphiques
        for ($i = 0; $i < 60; $i++) {
            $product  = $products->random();
            $quantity = random_int(1, 2);
            $ttc      = (float) $product->price;
            $ht       = round($ttc / (1 + (float) $product->vat_rate / 100), 2);

            ProductSale::create([
                'product_id'     => $product->id,
                // Une vente sur trois n'est rattachée à personne, comme au studio
                'user_id'        => random_int(0, 2) ? collect($clients)->random()->id : null,
                'quantity'       => $quantity,
                'unit_price_ht'  => $ht,
                'unit_price_ttc' => $ttc,
                'total_ht'       => round($ht * $quantity, 2),
                'total_ttc'      => round($ttc * $quantity, 2),
                'sold_at'        => CarbonImmutable::now()->subDays(random_int(1, 540))->toDateString(),
            ]);
        }
    }

    /** Prochain jour ouvré, en sautant dimanche et lundi (studio fermé). */
    private function nextOpenDay(int $inDays): CarbonImmutable
    {
        $date = CarbonImmutable::today()->addDays($inDays);

        while (in_array($date->dayOfWeek, [0, 1], true)) {
            $date = $date->addDay();
        }

        return $date;
    }
}
