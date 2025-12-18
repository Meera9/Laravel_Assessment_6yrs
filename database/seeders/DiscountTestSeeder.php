<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use UserDiscounts\Models\Discount;

class DiscountTestSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    : void
    {
        Discount::query()->truncate();

        Discount::create(['name' => 'Test 10%', 'percentage' => 10, 'active' => true]);
        Discount::create(['name' => 'Test 20%', 'percentage' => 20, 'active' => true]);
    }
}
