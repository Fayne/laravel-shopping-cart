<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table = config('shopping-cart.models.cart_items.table');
        $cartTableName = config('laravel-cart.carts.table');

        Schema::create($table, function (Blueprint $table) use ($cartTableName) {
            $table->id();
            $table->integer('quantity')->unsigned()->default(0);
            $table->string('sku')->nullable();
            $table->string('type')->nullable();
            $table->string('name')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('weight', 12)->default(1);
            $table->decimal('total_weight', 12)->default(0);
            $table->decimal('base_total_weight', 12)->default(0);

            $table->decimal('price', 12)->default(1);
            $table->decimal('base_price', 12)->default(0);
            $table->decimal('custom_price', 12)->default(0);

            $table->decimal('total', 12)->default(0);
            $table->decimal('base_total', 12)->default(0);

            $table->decimal('discount_percent', 12)->default(0);
            $table->decimal('discount_amount', 12)->default(0);
            $table->decimal('base_discount_amount', 12)->default(0);

            $table->json('additional')->nullable();

            $table->morphs('itemable');
            $table->boolean('selected')->default(false);
            $table->integer('cart_id')->unsigned();
            $table->foreign('cart_id')->references('id')->on($cartTableName)->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('shopping-cart.models.cart_items.table', 'cart_items'));
    }
}
