<?php

namespace UserDiscounts\Services;

use Illuminate\Support\Facades\DB;
use UserDiscounts\Events\DiscountApplied;

class DiscountService
{
    public function eligibleFor($user)
    : bool
    {
        return $user->discounts()
            ->where('revoked', false)
            ->whereHas('discount', function ($q) {
                $q->where('is_active', true)
                    ->where(function ($x) {
                        $x->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->exists();
    }

    public function apply($user, float $amount)
    : float
    {
        return DB::transaction(function () use ($user, $amount) {

            // 🔒 Lock rows + filter ONLY valid discounts
            $discounts = $user->discounts()
                ->lockForUpdate()
                ->where('revoked', false)
                ->whereHas('discount', function ($q) {
                    $q->where('is_active', true)
                        ->where(function ($x) {
                            $x->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                })
                ->with('discount') // ✅ eager load
                ->get();

            $totalPercent = 0;

            foreach ($discounts as $ud) {

                // Enforce per-user usage cap
                if ( $ud->usage_cap !== null && $ud->usage_count >= $ud->usage_cap ) {
                    continue;
                }

                $totalPercent += $ud->discount->percentage;

                // Increment usage safely inside transaction
                $ud->increment('usage_count');
            }

            // ✅ Correct config keys (underscore, not dash)
            $maxPercentage = config('user_discounts.max_percentage', 100);
            $rounding = config('user_discounts.rounding', 2);

            $totalPercent = min($totalPercent, $maxPercentage);

            $final = round(
                $amount * (1 - ($totalPercent / 100)),
                $rounding
            );

            event(new DiscountApplied($user->id, $amount, $final));

            return $final;
        });
    }
}
