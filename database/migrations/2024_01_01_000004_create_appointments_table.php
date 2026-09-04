<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rendez-vous et contre-propositions.
 * Le prix est figé à la création : une modification ultérieure du tarif de la
 * prestation ne doit pas changer le montant annoncé au client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_min');

            $table->string('status', 20)->default('pending')->index();
            // pending | confirmed | refused | cancelled
            // counter_proposed | counter_accepted | counter_refused | completed

            $table->decimal('service_price', 8, 2);
            $table->boolean('is_late_night')->default(false);   // après 20h : tarif doublé
            $table->decimal('debt_snapshot', 8, 2)->default(0);
            $table->decimal('total_due', 8, 2);

            $table->text('client_comment')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_ip', 45)->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancelled_by', 10)->nullable();

            $table->timestamps();
            $table->index(['starts_at', 'status']);
        });

        Schema::create('appointment_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_accepted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_proposals');
        Schema::dropIfExists('appointments');
    }
};
