<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id','sum','date'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ordermeals()
    {
        return $this->hasMany(OrderMeal::class, 'order_id');
    }
}
