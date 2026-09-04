<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le calendrier client s'alimente UNIQUEMENT de ce que le barbier ouvre ici.
 * 0 = dimanche ... 6 = samedi (convention Carbon).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique(['weekday', 'start_time']);
        });

        Schema::create('availability_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label', 60)->nullable();
            $table->timestamps();
        });

        // Congés (closed) ou ouverture exceptionnelle (open)
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('label', 60)->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_breaks');
        Schema::dropIfExists('availabilities');
    }
};
