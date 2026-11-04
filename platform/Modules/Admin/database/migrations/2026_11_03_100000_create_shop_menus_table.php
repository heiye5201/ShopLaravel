<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('placement')->nullable()->unique();
            $table->json('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_menus');
    }
};
