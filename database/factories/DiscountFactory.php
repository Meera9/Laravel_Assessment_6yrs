<?php

namespace Database\Factories;

use UserDiscounts\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition()
    {
        return [
            'name'        => $this->faker->word,
            'percentage'  => $this->faker->numberBetween(1, 50),
            'active'      => true,
            'stack_order' => $this->faker->numberBetween(1, 5),
            'usage_cap'   => $this->faker->optional()->numberBetween(1, 10),
        ];
    }
}
