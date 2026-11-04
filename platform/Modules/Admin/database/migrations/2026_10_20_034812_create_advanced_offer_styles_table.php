<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advanced_offer_styles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->boolean('enabled')->default(false)->index();
            $table->string('scope', 20);
            $table->json('product_ids');
            $table->json('variant_ids');
            $table->json('tiers');
            $table->json('design');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advanced_offer_styles');
    }
};
