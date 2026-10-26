<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visuel « avant » d'une prestation.
 *
 * Le champ existant devient le « après » : c'est lui qu'on affiche seul quand
 * il n'y a pas de couple, et c'est aussi le visuel déjà en place sur toutes
 * les prestations. Aucune reprise de données n'est donc nécessaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('image_before_path')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('image_before_path');
        });
    }
};
