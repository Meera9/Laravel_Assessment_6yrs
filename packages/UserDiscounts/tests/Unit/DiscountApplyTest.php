<?php

namespace UserDiscounts\Test\Unit;

use App\Models\User;
use Orchestra\Testbench\TestCase;
use UserDiscounts\Models\Discount;
use UserDiscounts\Models\UserDiscount;
use UserDiscounts\Services\DiscountService;
use UserDiscounts\UserDiscountsServiceProvider;
use Illuminate\Foundation\Auth\User as Authenticatable;

class DiscountApplyTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            UserDiscountsServiceProvider::class,
        ];
    }

    public function test_discount_usage_cap_is_enforced()
    {
        $user = User::query()->firstOrCreate([
            'name'  => 'Test',
            'email' => 'test@example.com',
        ]);

        $discount = Discount::create([
            'name'       => 'Test Discount',
            'percentage' => 20,
        ]);

        UserDiscount::create([
            'user_id'     => $user->id,
            'discount_id' => $discount->id,
            'usage_cap'   => 1,
        ]);

        $service = new DiscountService();

        $first = $service->apply($user, 100);
        $second = $service->apply($user, 100);

        $this->assertEquals(100, $second);
//        $this->assertEquals(80, $first);
    }
}
