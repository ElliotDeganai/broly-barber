<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Point de passage unique pour toutes les images.
 *
 * Redimensionne et compresse plutôt que de rejeter : une photo de téléphone
 * fait 8 Mo en 4032 × 3024, demander au barbier de la réduire lui-même est le
 * meilleur moyen qu'il n'alimente jamais sa galerie.
 *
 * Implémenté avec l'extension GD, présente dans l'image Docker de Sail comme
 * sur le VPS. Pas de dépendance externe : le traitement se limite à redimensionner
 * et compresser, ce qui ne justifie pas une bibliothèque dont l'API change de
 * version majeure en version majeure.
 */
class ImageUploadService
{
    /** Largeur max, hauteur max, poids visé en kilo-octets. */
    public const PRESETS = [
        'hero'    => ['width' => 2000, 'height' => 1600, 'target_kb' => 420],
        'gallery' => ['width' => 1600, 'height' => 1600, 'target_kb' => 300],
        'service' => ['width' => 1200, 'height' => 1200, 'target_kb' => 240],
        'product' => ['width' => 1200, 'height' => 1200, 'target_kb' => 220],
        'content' => ['width' => 1800, 'height' => 1400, 'target_kb' => 350],
    ];

    /** Qualités essayées dans l'ordre jusqu'à tenir sous le poids visé. */
    private const QUALITY_STEPS = [85, 78, 70, 62, 55];

    /**
     * Largeurs générées en plus de l'originale.
     *
     * Le navigateur choisit la plus adaptée à l'écran : un téléphone ne
     * télécharge pas une image de 1600 px pour l'afficher en 380.
     */
    public const VARIANTS = [400, 800, 1200];

    /** Largeur de l'aperçu flou affiché pendant le chargement. */
    private const LQIP_WIDTH = 24;

    public function store(UploadedFile $file, string $directory, string $preset = 'content'): string
    {
        $config = self::PRESETS[$preset] ?? self::PRESETS['content'];

        [$source, $hasAlpha] = $this->open($file);
        $source = $this->fixOrientation($source, $file->getRealPath());
        $resized = $this->scaleDown($source, $config['width'], $config['height'], $hasAlpha);

        if ($resized !== $source) {
            imagedestroy($source);
        }

        $extension = $hasAlpha ? 'png' : 'jpg';
        $base      = trim($directory, '/') . '/' . Str::uuid();
        $path      = "{$base}.{$extension}";

        // Une image transparente reste en PNG : la convertir en JPEG lui
        // ajouterait un fond blanc, ce qui ruine un logo détouré.
        $binary = $hasAlpha
            ? $this->encodePng($resized)
            : $this->encodeJpeg($resized, $config['target_kb'] * 1024);

        Storage::disk('public')->put($path, $binary);

        $this->writeVariants($resized, $base, $extension, $hasAlpha, $config);

        imagedestroy($resized);

        return $path;
    }

    /**
     * Écrit les déclinaisons plus étroites et l'aperçu flou.
     *
     * Les fichiers suivent une convention de nommage — base-400.jpg,
     * base-lqip.jpg — pour qu'on les retrouve sans colonne supplémentaire
     * en base de données.
     */
    private function writeVariants($image, string $base, string $extension, bool $hasAlpha, array $config): void
    {
        $sourceWidth = imagesx($image);

        foreach (self::VARIANTS as $width) {
            // Inutile d'agrandir : une déclinaison plus large que l'originale
            // pèserait davantage sans rien apporter.
            if ($width >= $sourceWidth) {
                continue;
            }

            $variant = $this->scaleDown($image, $width, $config['height'], $hasAlpha);

            Storage::disk('public')->put(
                "{$base}-{$width}.{$extension}",
                $hasAlpha ? $this->encodePng($variant) : $this->encodeJpeg($variant, 200 * 1024),
            );

            imagedestroy($variant);
        }

        // Aperçu : une vignette minuscule, étirée et floutée par le navigateur.
        // Quelques centaines d'octets, affichée le temps que l'image arrive.
        $lqip = $this->scaleDown($image, self::LQIP_WIDTH, self::LQIP_WIDTH * 4, $hasAlpha);

        Storage::disk('public')->put(
            "{$base}-lqip.jpg",
            $this->encodeJpeg($lqip, 4 * 1024),
        );

        imagedestroy($lqip);
    }

    /** Supprime une image ET toutes ses déclinaisons. */
    public function deleteWithVariants(?string $path): void
    {
        if (! $path) {
            return;
        }

        $this->delete($path);

        $base      = preg_replace('/\.[^.]+$/', '', $path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        foreach (self::VARIANTS as $width) {
            $this->delete("{$base}-{$width}.{$extension}");
        }

        $this->delete("{$base}-lqip.jpg");
    }

    /** Stocke la nouvelle image AVANT d'effacer l'ancienne : rien n'est perdu si l'écriture échoue. */
    public function replace(UploadedFile $file, ?string $previousPath, string $directory, string $preset = 'content'): string
    {
        $path = $this->store($file, $directory, $preset);
        $this->deleteWithVariants($previousPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function maxUploadKb(): int
    {
        return 12288;   // 12 Mo : au-delà, c'est le serveur qu'on protège
    }

    public static function rules(bool $required = false): array
    {
        return array_filter([
            $required ? 'required' : 'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:' . self::maxUploadKb(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Traitement GD                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Ouvre le fichier et indique s'il comporte de la transparence.
     *
     * @return array{0: \GdImage, 1: bool}
     */
    private function open(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException("Fichier image illisible : {$file->getClientOriginalName()}");
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            default        => false,
        };

        if ($image === false) {
            throw new RuntimeException('Format d\'image non pris en charge.');
        }

        // Le JPEG ne peut pas être transparent, inutile de l'analyser.
        $hasAlpha = $info[2] !== IMAGETYPE_JPEG && $this->detectAlpha($image);

        return [$image, $hasAlpha];
    }

    /**
     * Cherche un pixel réellement transparent.
     *
     * Un PNG peut être déclaré en truecolor avec canal alpha sans rien de
     * transparent : convertir ces images-là en JPEG fait gagner beaucoup de
     * poids. On échantillonne au lieu de tout parcourir — sur une image de
     * 4000 px, un balayage complet coûterait plusieurs secondes.
     */
    private function detectAlpha($image): bool
    {
        $width  = imagesx($image);
        $height = imagesy($image);

        $stepX = max(1, (int) ($width / 120));
        $stepY = max(1, (int) ($height / 120));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                // 127 = totalement transparent, 0 = opaque
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 8) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Redresse l'image d'après ses données EXIF.
     *
     * Une photo prise en tenant le téléphone de côté est stockée à l'endroit
     * avec une consigne de rotation. GD ignore cette consigne : sans ce
     * traitement, la photo apparaît couchée après conversion.
     */
    private function fixOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        $angle = match ($orientation) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Réduit l'image pour tenir dans le cadre, en conservant les proportions.
     * N'agrandit jamais : une petite image nette le reste.
     */
    private function scaleDown($image, int $maxWidth, int $maxHeight, bool $keepAlpha = false)
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $ratio  = min($maxWidth / $width, $maxHeight / $height, 1);

        if ($ratio >= 1) {
            return $image;
        }

        $newWidth  = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($keepAlpha) {
            // Sans ces trois lignes, GD écrase le canal alpha au redimensionnement
            // et les zones transparentes ressortent en noir.
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            // Fond blanc : sans lui, une image sans alpha convertie en JPEG
            // hérite d'un fond noir.
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /** Encode en JPEG, en baissant la qualité jusqu'à tenir sous le poids visé. */
    private function encodeJpeg($image, int $targetBytes): string
    {
        $binary = '';

        foreach (self::QUALITY_STEPS as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $binary = (string) ob_get_clean();

            if (strlen($binary) <= $targetBytes) {
                break;
            }
        }

        return $binary;
    }

    /**
     * Encode en PNG avec compression maximale.
     *
     * Le PNG est sans perte : on ne peut pas viser un poids comme en JPEG.
     * Le redimensionnement en amont fait donc l'essentiel du travail.
     */
    private function encodePng($image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}
