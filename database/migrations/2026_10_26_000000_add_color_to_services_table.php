<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couleur de repérage d'une prestation dans le planning.
 *
 * Nullable : les prestations existantes gardent une couleur dérivée de leur
 * identifiant tant que le barbier n'en a pas choisi une, le calendrier n'est
 * donc jamais monochrome au lendemain de la migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // 7 caractères : « #RRGGBB »
            $table->string('color', 7)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
