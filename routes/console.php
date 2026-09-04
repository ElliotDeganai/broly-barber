<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
| Depuis Laravel 11, le planificateur se déclare ici et non plus dans un
| Console\Kernel. Sur le serveur, une seule entrée cron suffit :
|
|   * * * * * cd /var/www/broly && php artisan schedule:run >> /dev/null 2>&1
*/

// 18 h : assez tôt pour que le client lise le rappel le soir même
Schedule::command('appointments:remind')
    ->dailyAt('18:00')
    ->timezone('Europe/Paris')
    // Empêche deux exécutions simultanées si la précédente traîne
    ->withoutOverlapping();
