<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shopping_cart_guest_locks', function (Blueprint $table): void {
            $table->char('id', 64)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_cart_guest_locks');
    }
};
