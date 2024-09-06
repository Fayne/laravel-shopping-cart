<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('instance', 50)->default('shopping')->comment('Available instances: shopping, wishlist');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('shopping_carts');
    }
};
