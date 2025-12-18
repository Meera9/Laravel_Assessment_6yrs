<?php

namespace UserDiscounts\Listeners;

use Illuminate\Support\Facades\Log;
use UserDiscounts\Events\DiscountApplied;
use UserDiscounts\Models\DiscountAudit;

class LogDiscountApplied
{
    public function handle(DiscountApplied $event)
    : void
    {
        Log::info(json_encode($event));

        DiscountAudit::create([
            'user_id'       => $event->userId,
            'action'        => 'applied',
            'amount_before' => $event->before,
            'amount_after'  => $event->after,
        ]);
    }
}
