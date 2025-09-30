<?php

namespace UserDiscounts\Facades;

use Illuminate\Support\Facades\Facade;

class UserDiscount extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'userdiscounts';
    }
}
