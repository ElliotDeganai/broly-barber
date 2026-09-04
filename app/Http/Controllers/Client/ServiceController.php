<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\SiteSetting;
use Inertia\Inertia;

/**
 * Prestations, page publique. Chaque prestation affiche son contenu et son
 * tarif, avec la mention avec ou sans restructuration : le client doit
 * comprendre le prix avant de réserver.
 */
class ServiceController extends Controller
{
    public function index()
    {
        return Inertia::render('Client/Services', [
            'content'  => SiteSetting::tree('services'),
            'services' => Service::active()->get([
                'id', 'name', 'slug', 'includes', 'description',
                'has_restructuration', 'price', 'duration_min', 'image_path',
            ]),
            'lateOffer' => [
                'hour'       => (int) SiteSetting::get('late_night_hour', 20),
                'multiplier' => (float) SiteSetting::get('late_night_multiplier', 2),
                'label'      => SiteSetting::get('late_night_label', 'Last minute'),
            ],
        ]);
    }
}
