<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopping_cart_skus', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->unsignedBigInteger('price_amount');
            $table->char('currency', 3)->default('CNY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_cart_skus');
    }
};
