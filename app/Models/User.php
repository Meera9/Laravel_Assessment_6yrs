<?php

namespace App\Models;

use App\Models\Traits\UserDiscountsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UserDiscounts\Models\UserDiscount;

class User extends Authenticatable
{
    use HasFactory, UserDiscountsTrait;

    protected $fillable = ['name', 'email', 'phone'];

    public function images()
    : HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(Image::class)->where('is_primary', true);
    }

    public function discounts()
    {
        return $this->hasMany(UserDiscount::class);
    }
}
