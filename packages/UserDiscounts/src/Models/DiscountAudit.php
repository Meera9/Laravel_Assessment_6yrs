<?php

namespace UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountAudit extends Model
{
    protected $fillable = ['action', 'discount_id', 'user_id', 'data'];
    protected $casts = ['data' => 'array'];
}
