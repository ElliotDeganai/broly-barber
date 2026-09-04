<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Un client bloqué garde l'accès à son compte mais ne peut plus réserver. */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_blocked) {
            return redirect()->route('account')
                ->with('error', "La réservation en ligne n'est plus disponible sur ce compte. Contactez le studio.");
        }

        return $next($request);
    }
}
