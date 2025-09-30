<?php

namespace App\Services;

use App\Models\{Discount, UserDiscount, DiscountAudit};
use Illuminate\Support\Facades\DB;

class DiscountManager
{
    // assign a discount to user
    public function assign(Discount $discount, $user)
    : void
    {
        UserDiscount::firstOrCreate([
            'user_id'     => $user->id,
            'discount_id' => $discount->id,
        ]);
        DiscountAudit::create([
            'action'      => 'assigned',
            'discount_id' => $discount->id,
            'user_id'     => $user->id,
        ]);
    }

    // revoke discount
    public function revoke(Discount $discount, $user)
    : void
    {
        UserDiscount::where('user_id', $user->id)
            ->where('discount_id', $discount->id)->delete();

        DiscountAudit::create([
            'action'      => 'revoked',
            'discount_id' => $discount->id,
            'user_id'     => $user->id,
        ]);
    }

    // check eligibility
    public function eligibleFor($user)
    : array
    {
        return Discount::where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereIn('id', UserDiscount::where('user_id', $user->id)->pluck('discount_id'))
            ->get()->all();
    }

    // apply discounts
    public function apply($user, float $amount)
    : float
    {
        $discounts = $this->eligibleFor($user);
        $final = $amount;

        DB::transaction(function () use ($discounts, $user, $amount, &$final) {
            foreach ($discounts as $discount) {
                $ud = UserDiscount::where('user_id', $user->id)
                    ->where('discount_id', $discount->id)
                    ->lockForUpdate()
                    ->first();

                if ( !$ud || $ud->usage_count >= $discount->per_user_limit ) {
                    continue;
                }

                $final = $final - ($final * ($discount->percentage / 100));
                $ud->increment('usage_count');

                DiscountAudit::create([
                    'action'      => 'applied',
                    'discount_id' => $discount->id,
                    'user_id'     => $user->id,
                    'data'        => ['base' => $amount, 'final' => $final],
                ]);
            }
        });

        return round($final, 2);
    }
}
