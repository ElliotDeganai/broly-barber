<?php

namespace App\Support;

use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Storage;

/**
 * Construit ce dont une balise <img> a besoin pour se charger progressivement :
 * l'URL, les déclinaisons disponibles et l'aperçu flou.
 *
 * Les déclinaisons sont déduites du nom du fichier — base-400.jpg — donc
 * aucune colonne supplémentaire n'est nécessaire en base.
 */
class ImagePayload
{
    /** @return array{url: string, srcset: ?string, lqip: ?string}|null */
    public static function make(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        $base      = preg_replace('/\.[^.]+$/', '', $path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $disk      = Storage::disk('public');

        $sources = [];

        foreach (ImageUploadService::VARIANTS as $width) {
            $variant = "{$base}-{$width}.{$extension}";

            // Les déclinaisons plus larges que l'originale n'ont pas été écrites
            if ($disk->exists($variant)) {
                $sources[] = asset('storage/' . $variant) . " {$width}w";
            }
        }

        $lqip = "{$base}-lqip.jpg";

        return [
            'url' => asset('storage/' . $path),
            // L'originale est déclarée à 2000w : le navigateur la retient quand
            // aucune déclinaison n'est assez large pour l'écran.
            'srcset' => $sources
                ? implode(', ', [...$sources, asset('storage/' . $path) . ' 2000w'])
                : null,
            'lqip' => $disk->exists($lqip) ? asset('storage/' . $lqip) : null,
        ];
    }
}
