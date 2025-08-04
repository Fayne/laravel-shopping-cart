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
        $userTableName = config('shopping-cart.models.users.table');
        $table = config('shopping-cart.models.carts.table');

        Schema::create($table, function (Blueprint $table) use ($userTableName) {
            $table->increments('id');

            $table->text('customer_name')->nullable();

            $table->string('shipping_method')->nullable();
            $table->string('coupon_code')->nullable();
            $table->boolean('is_gift')->default(0);
            $table->boolean('is_temp')->default(false);
            $table->integer('items_count')->nullable();
            $table->decimal('items_qty', 12)->nullable();

            $table->decimal('grand_total', 12)->default(0)->nullable();
            $table->decimal('base_grand_total', 12)->default(0)->nullable();

            $table->decimal('sub_total', 12)->default(0)->nullable();
            $table->decimal('base_sub_total', 12)->default(0)->nullable();

            $table->decimal('discount', 12)->default(0)->nullable();
            $table->decimal('base_discount', 12)->default(0)->nullable();

            $table->string('checkout_method')->nullable();
            $table->boolean('is_active')->nullable()->default(1);
            $table->dateTime('conversion_time')->nullable();

            $table->ulid('customer_id')->nullable();
            
            // Only add foreign key constraint if the users table exists
            if (Schema::hasTable($userTableName)) {
                $table->foreign('customer_id')->references('id')->on($userTableName)->onDelete('cascade');
            }

            $table->string('session_id')->nullable();
            $table->json('metadata')->nullable();

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
        Schema::dropIfExists(config('shopping-cart.models.carts.table', 'carts'));
    }
};
