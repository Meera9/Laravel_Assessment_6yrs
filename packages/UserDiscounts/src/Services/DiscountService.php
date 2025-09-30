<?php

namespace UserDiscounts\Services;

use Illuminate\Support\Facades\DB;
use UserDiscounts\Models\Discount;
use UserDiscounts\Models\UserDiscount;
use UserDiscounts\Models\DiscountAudit;
use UserDiscounts\Events\DiscountAssigned;
use UserDiscounts\Events\DiscountRevoked;
use UserDiscounts\Events\DiscountApplied;

class DiscountService
{
    public function assign($userId, Discount $discount)
    : void
    {
        UserDiscount::firstOrCreate([
            'user_id'     => $userId,
            'discount_id' => $discount->id,
        ]);

        DiscountAudit::create([
            'user_id'     => $userId,
            'discount_id' => $discount->id,
            'action'      => 'assigned',
        ]);

        event(new DiscountAssigned($userId, $discount));
    }

    public function revoke($userId, Discount $discount)
    : void
    {
        UserDiscount::where('user_id', $userId)
            ->where('discount_id', $discount->id)
            ->delete();

        DiscountAudit::create([
            'user_id'     => $userId,
            'discount_id' => $discount->id,
            'action'      => 'revoked',
        ]);

        event(new DiscountRevoked($userId, $discount));
    }

    public function eligibleFor($userId)
    : array
    {
        return UserDiscount::with('discount')
            ->where('user_id', $userId)
            ->get()
            ->filter(fn($ud) => $ud->discount->active &&
                (!$ud->discount->expires_at || $ud->discount->expires_at->isFuture()) &&
                (!$ud->usage_limit || $ud->usage_count < $ud->usage_limit)
            )
            ->pluck('discount')
            ->all();
    }

    public function apply($userId, float $price)
    : float
    {
        return DB::transaction(function () use ($userId, $price) {
            $discounts = $this->eligibleFor($userId);

            if ( empty($discounts) ) {
                return $price;
            }

            // Deterministic stacking: sort by percentage descending
            usort($discounts, fn($a, $b) => $b->percentage <=> $a->percentage);

            $cap = config('user_discounts.max_percentage_cap', 50);
            $totalPercentage = min(array_sum(array_column($discounts, 'percentage')), $cap);

            $discountedPrice = $price * (1 - $totalPercentage / 100);

            // Rounding
            $round = config('user_discounts.rounding', 2);
            $discountedPrice = round($discountedPrice, $round);

            // Increment usage count safely
            foreach ($discounts as $discount) {
                $ud = UserDiscount::where('user_id', $userId)
                    ->where('discount_id', $discount->id)
                    ->lockForUpdate()
                    ->first();

                if ( $ud && (!$ud->usage_limit || $ud->usage_count < $ud->usage_limit) ) {
                    $ud->increment('usage_count');
                    DiscountAudit::create([
                        'user_id'     => $userId,
                        'discount_id' => $discount->id,
                        'action'      => 'applied',
                    ]);
                    event(new DiscountApplied($userId, $discount));
                }
            }

            return $discountedPrice;
        });
    }
}
