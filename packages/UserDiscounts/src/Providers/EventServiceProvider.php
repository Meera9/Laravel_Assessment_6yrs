<?php

namespace UserDiscounts\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use UserDiscounts\Events\DiscountApplied;
use UserDiscounts\Listeners\LogDiscountApplied;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
//        DiscountAssigned::class => [
//            LogDiscountAssigned::class,
//        ],
//        DiscountRevoked::class  => [
//            LogDiscountRevoked::class,
//        ],
        DiscountApplied::class => [
            LogDiscountApplied::class,
        ],
    ];
}
