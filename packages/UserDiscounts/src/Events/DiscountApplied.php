<?php

namespace UserDiscounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public int $userId;
    public int $discountId;
    public float $amountBefore;
    public ?float $amountAfter;

    public function __construct(int $userId, int $discountId, float $amountBefore, ?float $amountAfter = null)
    {
        $this->userId = $userId;
        $this->discountId = $discountId;
        $this->amountBefore = $amountBefore;
        $this->amountAfter = $amountAfter;
    }
}
