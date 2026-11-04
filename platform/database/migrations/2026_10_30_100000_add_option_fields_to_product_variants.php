<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('variant_options')->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('sku', 100)->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->json('option_values')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['sku', 'image_path', 'compare_at_price', 'track_inventory', 'option_values']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variant_options');
        });
    }
};
