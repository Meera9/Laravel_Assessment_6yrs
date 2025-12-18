<?php

namespace UserDiscounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public float $amountBefore,
        public float $amountAfter
    )
    {
    }
}
