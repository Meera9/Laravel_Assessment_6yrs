<?php

namespace UserDiscounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $discountId
    )
    {
    }
}
