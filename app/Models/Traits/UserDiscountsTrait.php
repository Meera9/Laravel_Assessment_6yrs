<?php

namespace App\Models\Traits;

use UserDiscounts\Models\Discount;
use UserDiscounts\Models\UserDiscount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait UserDiscountsTrait
{
    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'user_discounts')
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    public function assignDiscount(Discount $discount)
    {
        $this->discounts()->syncWithoutDetaching([
            $discount->id => ['usage_count' => 0]
        ]);
    }

    public function revokeDiscount(Discount $discount)
    {
        $this->discounts()->detach($discount->id);
    }

    public function eligibleFor(Discount $discount)
    {
        return $discount->active && $this->discounts()->where('discount_id', $discount->id)->exists();
    }

    public function applyDiscount(Discount $discount, float $amount): float
    {
        if (! $this->eligibleFor($discount)) {
            return $amount;
        }

        return DB::transaction(function () use ($discount, $amount) {
            $pivot = $this->discounts()->where('discount_id', $discount->id)->first()->pivot;

            if ($discount->usage_cap && $pivot->usage_count >= $discount->usage_cap) {
                return $amount; // usage cap reached
            }

            $discounted = round($amount * (1 - $discount->percentage / 100), 2);

            // increment usage
            $this->discounts()->updateExistingPivot($discount->id, [
                'usage_count' => $pivot->usage_count + 1
            ]);

            return $discounted;
        });
    }

    public function applyAllDiscounts(float $amount): float
    {
        $discounts = $this->discounts()->where('active', true)
            ->orderBy('stack_order', 'asc')
            ->get();

        foreach ($discounts as $discount) {
            $amount = $this->applyDiscount($discount, $amount);
        }

        return $amount;
    }
}
