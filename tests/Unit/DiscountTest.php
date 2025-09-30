<?php

namespace Tests\Unit;

use Tests\TestCase;
use UserDiscounts\Models\Discount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class DiscountTest extends TestCase
{
    #[Test]
    public function it_assigns_discount_to_user()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'Test 10%',
            'percentage'  => 10,
            'active'      => true,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);

        $this->assertDatabaseHas('user_discounts', [
            'user_id'     => $user->id,
            'discount_id' => $discount->id,
        ]);
    }

    #[Test]
    public function it_revokes_discount_from_user()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'Test',
            'percentage'  => 5,
            'active'      => true,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);
        $user->revokeDiscount($discount);

        $this->assertDatabaseMissing('user_discounts', [
            'user_id'     => $user->id,
            'discount_id' => $discount->id,
        ]);
    }

    #[Test]
    public function it_checks_user_eligibility()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'Test',
            'percentage'  => 20,
            'active'      => true,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);
        $this->assertTrue($user->eligibleFor($discount));

        $discount->update(['active' => false]);
        $this->assertFalse($user->eligibleFor($discount));
    }

    #[Test]
    public function it_applies_discount_correctly_with_usage_cap()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'Cap 10%',
            'percentage'  => 10,
            'active'      => true,
            'usage_cap'   => 2,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);

        $amount = 100;
        $this->assertEquals(90, $user->applyDiscount($discount, $amount));
        $this->assertEquals(90, $user->applyDiscount($discount, $amount));
        $this->assertEquals(100, $user->applyDiscount($discount, $amount));
    }

    #[Test]
    public function it_applies_multiple_discounts_deterministically()
    {
        $user = User::factory()->create();

        $d1 = Discount::create([
            'name'        => 'D1',
            'percentage'  => 10,
            'active'      => true,
            'stack_order' => 1,
        ]);

        $d2 = Discount::create([
            'name'        => 'D2',
            'percentage'  => 20,
            'active'      => true,
            'stack_order' => 2,
        ]);

        $user->assignDiscount($d1);
        $user->assignDiscount($d2);

        $amount = 200;
        $this->assertEquals(144, $user->applyAllDiscounts($amount)); // 10% then 20%
    }

    #[Test]
    public function revoked_discounts_are_not_applied()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'D1',
            'percentage'  => 15,
            'active'      => true,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);
        $user->revokeDiscount($discount);

        $amount = 100;
        $this->assertEquals(100, $user->applyDiscount($discount, $amount));
    }

    #[Test]
    public function expired_or_inactive_discounts_are_ignored()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'D1',
            'percentage'  => 10,
            'active'      => false,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);

        $amount = 100;
        $this->assertEquals(100, $user->applyDiscount($discount, $amount));
    }

    #[Test]
    public function concurrent_apply_does_not_double_increment_usage()
    {
        $user = User::factory()->create();
        $discount = Discount::create([
            'name'        => 'Concurrent',
            'percentage'  => 10,
            'active'      => true,
            'usage_cap'   => 1,
            'stack_order' => 1,
        ]);

        $user->assignDiscount($discount);

        DB::transaction(function () use ($user, $discount) {
            $this->assertEquals(90, $user->applyDiscount($discount, 100));
        });

        DB::transaction(function () use ($user, $discount) {
            $this->assertEquals(100, $user->applyDiscount($discount, 100));
        });
    }
}
