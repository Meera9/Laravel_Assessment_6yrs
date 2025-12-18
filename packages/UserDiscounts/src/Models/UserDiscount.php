<?php

namespace UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;

class UserDiscount extends Model
{
    protected $table = 'user_discounts';

    protected $fillable = [
        'user_id',
        'discount_id',
        'usage_cap',
        'usage_count',
        'revoked',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
