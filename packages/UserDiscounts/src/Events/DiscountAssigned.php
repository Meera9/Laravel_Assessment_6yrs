<?php

namespace UserDiscounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountAssigned
{
    use Dispatchable, SerializesModels;

    public int $userId;
    public int $discountId;

    public function __construct(int $userId, int $discountId)
    {
        $this->userId = $userId;
        $this->discountId = $discountId;
    }
}
