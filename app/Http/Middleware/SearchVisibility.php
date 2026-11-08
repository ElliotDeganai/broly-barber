<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visibilité du site dans les moteurs de recherche.
 *
 * L'en-tête part sur toutes les réponses tant que `seo.indexable` est faux,
 * ce qui est le défaut. Réglages et explications dans config/seo.php.
 *
 * On passe par un en-tête HTTP plutôt que par la seule balise <meta> parce
 * qu'il couvre aussi ce qui n'est pas une page HTML : PDF, flux, exports.
 *
 * Attention à ne PAS compléter ce dispositif par un « Disallow: / » dans
 * robots.txt. Interdire l'exploration empêche le robot de lire la directive
 * noindex : Google l'écrit lui-même, une page bloquée « ne verra jamais la
 * règle noindex » et peut rester affichée, réduite à son adresse nue. Pour
 * sortir de l'index il faut au contraire laisser le robot entrer.
 */
class SearchVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('seo.indexable')) {
            $response->headers->set('X-Robots-Tag', config('seo.consigne'));
        }

        return $response;
    }
}
