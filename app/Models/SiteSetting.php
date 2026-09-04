<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Source unique du contenu et des paramètres du site.
 *
 * Convention de clé, TOUJOURS plate :
 *   contenu   -> page_section_champ   (home_hero_title)
 *   paramètre -> nom_simple           (cancellation_hours)
 *
 * Jamais de point : Laravel le lit comme un séparateur de tableau imbriqué et
 * casse la validation comme la récupération des fichiers téléversés.
 */
class SiteSetting extends Model
{
    public const TYPES = ['text', 'textarea', 'image', 'icon', 'number', 'url', 'color'];

    protected $fillable = [
        'key', 'value', 'type', 'group', 'label', 'help', 'position', 'is_system',
    ];

    /** Depuis Laravel 11, les casts se déclarent dans une méthode. */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    public static function flush(): void
    {
        Cache::forget('site_settings');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group', 'key');
    }

    /** Chemin de stockage transformé en URL affichable. */
    public function getUrlAttribute(): ?string
    {
        return $this->type === 'image' && $this->value
            ? asset('storage/' . ltrim($this->value, '/'))
            : null;
    }

    /**
     * Toutes les valeurs, images résolues en URL absolues.
     *
     * Le cache ne retient que les CHEMINS, jamais les URL construites. Mettre
     * en cache le résultat de asset() gèle le domaine : changer APP_URL ou
     * ASSET_URL n'aurait aucun effet tant que le cache n'est pas vidé à la
     * main, ce qui est impossible à deviner quand on cherche pourquoi une
     * image ne s'affiche pas.
     */
    public static function all_cached(): array
    {
        $raw = Cache::rememberForever('site_settings', function () {
            return static::all()->mapWithKeys(fn ($s) => [
                $s->key => ['value' => $s->value, 'type' => $s->type],
            ])->toArray();
        });

        return collect($raw)
            ->map(fn ($item) => ($item['type'] ?? null) === 'image' && ! empty($item['value'])
                ? asset('storage/' . ltrim($item['value'], '/'))
                : ($item['value'] ?? null))
            ->all();
    }

    public static function get(string $key, $default = null)
    {
        return static::all_cached()[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Contenu d'une page en arborescence. Non mis en cache : les URL y sont
     * construites à chaque appel, donc toujours sur le domaine courant.
     */
    public static function tree(string $page): array
    {
        $out = [];

        foreach (static::where('group', 'like', $page . '_%')->get() as $setting) {
            $section = Str::after($setting->group, $page . '_');
            $field   = Str::after($setting->key, $setting->group . '_');

            $out[$section][$field] = $setting->type === 'image' ? $setting->url : $setting->value;
        }

        return $out;
    }
}
