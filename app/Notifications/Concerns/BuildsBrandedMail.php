<?php

namespace App\Notifications\Concerns;

use App\Models\SiteSetting;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Construction des emails du studio.
 *
 * Toutes les notifications partagent le même gabarit : un seul endroit à
 * modifier si la charte évolue, et aucun risque qu'un message parte avec une
 * mise en page différente des autres.
 */
trait BuildsBrandedMail
{
    /**
     * @param  array<int, string>     $lines    Paragraphes du corps
     * @param  array<string, string>  $details  Récapitulatif en tableau
     */
    protected function branded(
        string $subject,
        string $title,
        array $lines,
        array $details = [],
        ?string $action = null,
        ?string $url = null,
        ?string $note = null,
        ?string $preview = null,
    ): MailMessage {
        // Les visuels viennent des réglages du site : un seul endroit à changer
        // pour que le site et les emails restent cohérents.
        $logo       = SiteSetting::get('logo');
        $background = SiteSetting::get('home_hero_image');

        return (new MailMessage())
            ->subject($subject)
            ->view('emails.message', compact(
                'title', 'lines', 'details', 'action', 'url', 'note', 'preview',
                'logo', 'background',
            ));
    }

    /** « samedi 12 septembre à 14h30 » */
    protected function humanDate(CarbonInterface $date): string
    {
        return $date->locale('fr_FR')->isoFormat('dddd D MMMM [à] HH[h]mm');
    }

    /** « 45,00 € » */
    protected function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ') . ' €';
    }
}
