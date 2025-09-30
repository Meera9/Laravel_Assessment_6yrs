<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use UserDiscounts\Models\Discount;

class DiscountController extends Controller
{

    public function test()
    {
        $user = User::first();
        $discount = Discount::first();

        // Assign discount
        $user->assignDiscount($discount);

        // Check eligibility
        $user->eligibleFor($discount); // true/false

        // Apply discount to amount
        $newAmount = $user->applyDiscount($discount, 100); // e.g., 100 -> 90

        $total = $user->applyAllDiscounts($newAmount);

        return response()->json(['data' => $total]);
    }

}
