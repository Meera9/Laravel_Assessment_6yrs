<?php

namespace UserDiscounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'percentage',
        'is_active',
        'expires_at',
    ];

    protected $casts = ['expires_at' => 'datetime'];
}
