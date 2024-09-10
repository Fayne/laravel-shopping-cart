<?php

namespace MobileNowGroup\ShoppingCart\Database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MobileNowGroup\ShoppingCart\Models\ShoppingCart;

class ShoppingCartFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ShoppingCart>
     */
    protected $model = ShoppingCart::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [];
    }
}
