<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs métier ajoutés à la table users de Breeze.
 * Rôles, dette, blocage et compteur de fidélité — repris du parcours utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->index();   // client | admin
            $table->string('phone', 30)->nullable();

            // Connexion sociale (Instagram / Facebook / TikTok)
            $table->string('provider', 20)->nullable();
            $table->string('provider_id', 100)->nullable();

            // Client créé au studio par le barbier : accès par lien magique
            $table->boolean('is_offline')->default(false);

            $table->decimal('debt_amount', 8, 2)->default(0);
            $table->text('debt_note')->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();

            // Fidélité : compteur dénormalisé, recalculé par LoyaltyService
            $table->unsignedInteger('visits_count')->default(0);
            $table->timestamp('last_completed_at')->nullable();

            $table->index(['provider', 'provider_id']);
        });

        Schema::create('magic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('channel', 10)->default('email');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'phone', 'provider', 'provider_id', 'is_offline',
                'debt_amount', 'debt_note', 'is_blocked', 'block_reason', 'blocked_at',
                'visits_count', 'last_completed_at',
            ]);
        });
    }
};
