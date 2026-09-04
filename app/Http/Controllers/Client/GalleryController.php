<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Galerie en carrousel : le client fait défiler et peut ouvrir une photo. */
class GalleryController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Client/Gallery', [
            'content'  => SiteSetting::tree('gallery'),
            'services' => Service::active()->get(['id', 'name']),
            'filter'   => $request->integer('service_id') ?: null,
            'items'    => GalleryItem::published()
                ->when($request->integer('service_id'), fn ($q, $id) => $q->where('service_id', $id))
                ->get(['id', 'path', 'alt', 'service_id'])
                ->map(fn ($item) => [
                    'id'  => $item->id,
                    'url' => $item->url,
                    'alt' => $item->alt,
                ]),
        ]);
    }
}
