<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use App\Models\Service;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Galerie du studio : les photos alimentent le carrousel public. */
class GalleryController extends Controller
{
    public function __construct(private ImageUploadService $images) {}

    public function index()
    {
        return Inertia::render('Admin/Gallery', [
            'items' => GalleryItem::with('service:id,name')->orderBy('position')->get()
                ->map(fn ($item) => [
                    'id'           => $item->id,
                    'url'          => $item->url,
                    'alt'          => $item->alt,
                    'service_id'   => $item->service_id,
                    'is_published' => $item->is_published,
                    'position'     => $item->position,
                ]),

            'services' => Service::active()->get(['id', 'name']),
        ]);
    }

    /** Téléversement multiple : le barbier ajoute une série après une journée. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'images'     => ['required', 'array', 'max:20'],
            'images.*'   => ImageUploadService::rules(required: true),
            'service_id' => ['nullable', 'exists:services,id'],
            'alt'        => ['nullable', 'string', 'max:180'],
        ]);

        $position = (int) GalleryItem::max('position');

        foreach ($request->file('images') as $file) {
            GalleryItem::create([
                'path'       => $this->images->store($file, 'gallery', 'gallery'),
                'alt'        => $data['alt'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'position'   => ++$position,
            ]);
        }

        return back()->with('success', 'Photos ajoutées.');
    }

    public function update(Request $request, GalleryItem $gallery)
    {
        $data = $request->validate([
            'alt'          => ['nullable', 'string', 'max:180'],
            'service_id'   => ['nullable', 'exists:services,id'],
            'is_published' => ['boolean'],
            'position'     => ['nullable', 'integer', 'min:0'],
        ]);

        $gallery->update($data);

        return back()->with('success', 'Photo mise à jour.');
    }

    public function destroy(GalleryItem $gallery)
    {
        $this->images->delete($gallery->path);
        $gallery->delete();

        return back()->with('success', 'Photo supprimée.');
    }
}
