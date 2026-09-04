<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Command;

/**
 * Rappel de la veille.
 *
 * Lancée chaque jour par le planificateur. Le champ reminded_at empêche
 * l'envoi en double si la commande tourne deux fois — ce qui arrive au
 * redémarrage d'un serveur ou lors d'un test manuel.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind';

    protected $description = 'Envoie un rappel aux clients dont le rendez-vous a lieu demain';

    public function handle(): int
    {
        $appointments = Appointment::with(['user', 'service'])
            ->whereIn('status', [Appointment::CONFIRMED, Appointment::COUNTER_ACCEPTED])
            ->whereDate('starts_at', today()->addDay())
            ->whereNull('reminded_at')
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            if (! $appointment->user->email) {
                continue;
            }

            $appointment->user->notify(new AppointmentReminder($appointment));
            $appointment->forceFill(['reminded_at' => now()])->save();

            $sent++;
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
