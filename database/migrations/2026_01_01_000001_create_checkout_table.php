<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('checkout', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(config('app.user_model', App\Models\User::class))->references('id')->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('currency')->default('CNY');
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->datetimes();
        });

        Schema::table('checkout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('checkout_id')->references('id')->on('checkout')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('sku_id')->nullable();
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 10, 2);
            $table->unsignedInteger('quantity');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('checkout');
        Schema::dropIfExists('checkout_items');
    }
};
