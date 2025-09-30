<?php

namespace UserDiscounts;

use Illuminate\Support\ServiceProvider;
use UserDiscounts\Services\DiscountService;

class UserDiscountsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/user_discounts.php' => config_path('user_discounts.php'),
        ], 'config');
    }

    public function register()
    {
        $this->app->singleton('userdiscounts', function () {
            return new DiscountService();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/user_discounts.php',
            'user_discounts'
        );
    }
}
