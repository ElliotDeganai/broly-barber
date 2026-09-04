<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\AvailabilityService;
use App\Support\ImagePayload;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accueil.
 *
 * Le wireframe décrit une page vitrine complète, pas seulement le bandeau :
 * bandeau, prestations, produits, galerie et FAQ s'enchaînent, chacune renvoyant
 * vers sa page dédiée. Un visiteur doit pouvoir tout comprendre sans naviguer.
 */
class HomeController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    public function __invoke(): Response
    {
        return Inertia::render('Client/Home', [
            'content' => SiteSetting::tree('home'),

            'services' => Service::active()->get([
                'id', 'name', 'slug', 'includes', 'description',
                'has_restructuration', 'price', 'duration_min', 'image_path',
            ])->map(fn ($service) => $service->only([
                'id', 'name', 'slug', 'includes', 'description',
                'has_restructuration', 'price', 'duration_min',
            ]) + ['image' => ImagePayload::make($service->image_path)]),

            // Quatre produits en aperçu : la page boutique montre tout
            'products' => ProductCategory::with('products')->orderBy('position')->get()
                ->flatMap(fn ($category) => $category->products->where('is_published', true))
                ->take(4)
                ->values()
                ->map(fn ($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'description' => $p->description,
                    'price'       => (float) $p->price,
                    'image'       => ImagePayload::make($p->photo_path),
                ]),

            // 14 photos : la bande défilante duplique la série, il en faut
            // assez pour que la boucle ne se remarque pas.
            'gallery' => GalleryItem::published()->take(14)->get(['id', 'path', 'alt'])
                ->map(fn ($item) => [
                    'id'    => $item->id,
                    'alt'   => $item->alt,
                    'image' => ImagePayload::make($item->path),
                ]),

            'faqs' => Faq::published()->take(8)->get(['id', 'question', 'answer']),

            // Prochain créneau réellement libre : concrétise les trois étapes
            // en montrant qu'un rendez-vous est disponible tout de suite.
            'nextSlot' => $this->nextSlot(),

            'lateOffer' => [
                'hour'       => (int) SiteSetting::get('late_night_hour', 20),
                'multiplier' => (float) SiteSetting::get('late_night_multiplier', 2),
            ],
        ]);
    }

    /**
     * Cherche le premier créneau ouvert, sur la prestation la plus courte.
     *
     * La plus courte parce qu'elle a le plus de chances de tenir dans un
     * interstice : annoncer une disponibilité qui n'existe que pour la coupe
     * longue serait trompeur.
     */
    private function nextSlot(): ?array
    {
        $service = Service::active()->orderBy('duration_min')->first();

        if (! $service) {
            return null;
        }

        $from = CarbonImmutable::today();
        $days = $this->availability->slotsFor($service, $from, $from->addDays(14));

        foreach ($days as $date => $times) {
            if (! $times) {
                continue;
            }

            return [
                'starts_at'    => CarbonImmutable::parse($date . ' ' . $times[0])->toIso8601String(),
                'service_name' => $service->name,
                'duration_min' => $service->duration_min,
                'price'        => (float) $service->price,
                'slug'         => $service->slug,
            ];
        }

        return null;
    }
}
