<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Prestations : intitulé, contenu, tarif, durée, visuel. */
class ServiceController extends Controller
{
    public function __construct(private ImageUploadService $images) {}

    public function index()
    {
        return Inertia::render('Admin/Services/Index', [
            'services' => Service::orderBy('position')->get()
                ->map(fn ($s) => $s->only([
                    'id', 'name', 'slug', 'includes', 'has_restructuration',
                    'price', 'duration_min', 'is_active', 'position',
                ]) + ['image' => $s->image_url]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Services/Form', ['service' => null]);
    }

    public function edit(Service $service)
    {
        return Inertia::render('Admin/Services/Form', [
            'service' => $service->only([
                'id', 'name', 'slug', 'includes', 'description', 'has_restructuration',
                'price', 'duration_min', 'is_active', 'position',
            ]) + ['image' => $service->image_url],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->store($request->file('image'), 'services', 'service');
        }

        $service = Service::create($data);

        return redirect()->route('admin.services.index')
            ->with('success', "« {$service->name} » créée.");
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validated($request, $service);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->replace(
                $request->file('image'), $service->image_path, 'services', 'service',
            );
        }

        $service->update($data);

        // Retour à la liste : rester sur le formulaire donne l'impression que
        // rien ne s'est passé, la page étant identique avant et après.
        return redirect()->route('admin.services.index')
            ->with('success', "« {$service->name} » enregistrée.");
    }

    public function destroy(Service $service)
    {
        // Une prestation liée à des rendez-vous ne peut pas disparaître : on la
        // désactive, ce qui la retire du site sans casser l'historique.
        if ($service->appointments()->exists()) {
            $service->update(['is_active' => false]);

            return back()->with('success', 'Prestation désactivée (des rendez-vous y sont liés).');
        }

        $this->images->delete($service->image_path);
        $service->delete();

        return back()->with('success', 'Prestation supprimée.');
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'slug'                => ['nullable', 'string', 'max:120', Rule::unique('services', 'slug')->ignore($service)],
            'includes'            => ['nullable', 'string', 'max:120'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'has_restructuration' => ['boolean'],
            'price'               => ['required', 'numeric', 'min:0'],
            'duration_min'        => ['required', 'integer', 'min:5', 'max:480'],
            'is_active'           => ['boolean'],
            'position'            => ['nullable', 'integer', 'min:0'],
            'image'               => ImageUploadService::rules(),
        ]);
    }
}
