<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prestations. Le prix est porté par la prestation (35 € coupe courte,
 * 45 € mi-courte, 55 € mi-longue, 65 € longue, 15 € barbe).
 *
 * `includes` porte la mention courte du contenu — « Ciseau + Tondeuse » — et
 * `description` explique ce qui est compris, avec ou sans restructuration, pour
 * que le client comprenne le prix avant de réserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->string('includes')->nullable();
            $table->text('description')->nullable();
            $table->boolean('has_restructuration')->default(true);
            $table->decimal('price', 8, 2);
            $table->unsignedSmallInteger('duration_min');
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('services');
    }
};
