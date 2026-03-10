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
            $table->ulid('id')->primary();
            $table->string('out_trade_no')->unique()->comment('System order number for payment gateways');
            $table->foreignIdFor(config('app.user_model', App\Models\User::class))->cascadeOnDelete();
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

        Schema::create('checkout_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('checkout_id')->constrained('checkout')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('sku_id')->constrained('product_skus')->cascadeOnDelete();
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
        Schema::dropIfExists('checkout_items');
        Schema::dropIfExists('checkout');
    }
};
