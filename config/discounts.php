<?php
return [
    // stacking order: 'highest_first' or 'sequential' (apply highest percentage first or in assign order)
    'stacking'               => env('DISCOUNT_STACKING', 'sequential'),

    // maximum combined percent across discounts (0-100)
    'max_percentage_cap'     => env('DISCOUNT_MAX_CAP', 50),

    // rounding: 'round', 'floor', 'ceil'
    'rounding'               => env('DISCOUNT_ROUNDING', 'round'),

    // default per-user usage limit if not set on discount
    'default_per_user_limit' => 100,
];
