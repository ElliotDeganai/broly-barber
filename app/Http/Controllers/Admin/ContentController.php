<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SettingGroup;
use App\Models\SiteSetting;
use App\Services\ImageUploadService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Édition du contenu et des réglages, tout depuis un seul écran.
 *
 * Les clés sont plates (home_hero_title) : un point serait interprété par
 * Laravel comme un séparateur de tableau imbriqué et casserait la validation
 * comme la récupération des fichiers.
 */
class ContentController extends Controller
{
    public function __construct(private ImageUploadService $images) {}

    public function index()
    {
        $settings = SiteSetting::orderBy('group')->orderBy('position')->get();
        $groups   = SettingGroup::orderBy('scope')->orderBy('position')->get();

        $sections = $groups->map(fn ($group) => [
            'key'    => $group->key,
            'label'  => $group->label,
            'help'   => $group->help,
            'scope'  => $group->scope,
            'fields' => $settings->where('group', $group->key)->values()
                ->map(fn ($s) => $this->field($s)),
        ])->values();

        // Filet de sécurité : un champ sans groupe déclaré resterait invisible
        $orphans = $settings->whereNotIn('group', $groups->pluck('key'))->values();

        if ($orphans->isNotEmpty()) {
            $sections->push([
                'key'    => '_orphans',
                'label'  => 'Champs sans section',
                'help'   => "Ces champs n'appartiennent à aucune section déclarée. Ils restent modifiables ici.",
                'scope'  => 'settings',
                'fields' => $orphans->map(fn ($s) => $this->field($s)),
            ]);
        }

        return Inertia::render('Admin/Content', [
            'sections' => $sections,
            'pages'    => Page::orderBy('title')->get(['id', 'slug', 'title', 'body', 'is_published']),
        ]);
    }

    public function update(Request $request)
    {
        // Tableaux récupérés en entier : la notation par clé échouerait sur les
        // underscores, que Laravel peut lire comme des séparateurs.
        $values = $request->input('values', []);
        $files  = $request->allFiles()['images'] ?? [];

        foreach ($values as $key => $value) {
            $setting = SiteSetting::where('key', $key)->first();

            if ($setting && $setting->type !== 'image') {
                $setting->update(['value' => $value]);
            }
        }

        foreach ($files as $key => $file) {
            $setting = SiteSetting::where('key', $key)->where('type', 'image')->first();

            if (! $setting) {
                continue;
            }

            // La nouvelle image est écrite AVANT que l'ancienne soit effacée
            $setting->update([
                'value' => $this->images->replace($file, $setting->value, 'content', 'content'),
            ]);
        }

        SiteSetting::flush();

        return back()->with('success', 'Contenu enregistré.');
    }

    public function updatePage(Request $request, Page $page)
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:180'],
            'body'         => ['nullable', 'string', 'max:20000'],
            'is_published' => ['boolean'],
            'image'        => ImageUploadService::rules(),
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->images->replace(
                $request->file('image'), $page->image_path, 'pages', 'content',
            );
        }

        // Le contenu vient d'un éditeur enrichi : on ne le stocke jamais brut
        $data['body'] = HtmlSanitizer::clean($data['body'] ?? null);

        $page->update($data);

        return back()->with('success', 'Page enregistrée.');
    }

    private function field(SiteSetting $setting): array
    {
        return [
            'key'   => $setting->key,
            'label' => $setting->label ?: $setting->key,
            'help'  => $setting->help,
            'type'  => $setting->type,
            'value' => $setting->type === 'image' ? null : $setting->value,
            'url'   => $setting->type === 'image' ? $setting->url : null,
        ];
    }
}
