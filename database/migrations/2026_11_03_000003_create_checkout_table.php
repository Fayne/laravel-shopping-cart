<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
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
            $table->string('tenant_id')->nullable()->index();
            $table->foreignIdFor(config('app.user_model', 'App\\Models\\User'));
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
            if (config('shopping-cart.models.skus.model') === \Fayne\ShoppingCart\Models\CartSku::class) {
                $table->ulid('product_id')->nullable();
                $table->foreignUlid('sku_id')->constrained('shopping_cart_skus')->restrictOnDelete();
            } else {
                $table->ulid('product_id')->nullable();
                $table->ulid('sku_id');
                if (Schema::hasTable('products')) {
                    $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
                }
                if (Schema::hasTable('product_skus')) {
                    $table->foreign('sku_id')->references('id')->on('product_skus')->restrictOnDelete();
                }
            }
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('discount_amount', 10, 2)->default(0);
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
