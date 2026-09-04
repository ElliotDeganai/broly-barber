<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenu éditable depuis /admin : textes, images, FAQ et pages libres.
 *
 * Clés PLATES en snake_case (home_hero_title) : un point serait interprété par
 * Laravel comme un séparateur de tableau imbriqué et casserait la validation
 * comme la récupération des fichiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label');
            $table->text('help')->nullable();
            $table->string('scope', 20)->default('content');   // content | settings
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('text');       // text|textarea|image|icon|number|url
            $table->string('group', 60)->default('general');
            $table->string('label')->nullable();
            $table->text('help')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_system')->default(false);      // référencé par le code
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // « Qui suis-je », mentions légales, RGPD, cookies, annulation
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('setting_groups');
    }
};
