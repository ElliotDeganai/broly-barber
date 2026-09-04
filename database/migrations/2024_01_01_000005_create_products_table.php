<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only Sayajin Store — vitrine seule, « disponible uniquement au studio ».
 * Les ventes sont saisies au studio depuis le back office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // « Cires brillantes »
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();   // prix commun à la gamme
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');                       // « Pomme — Broly Wax »
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price_ht', 8, 2);
            $table->decimal('unit_price_ttc', 8, 2);
            $table->decimal('total_ht', 8, 2);
            $table->decimal('total_ttc', 8, 2);
            $table->date('sold_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
