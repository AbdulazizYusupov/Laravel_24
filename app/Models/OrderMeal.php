<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMeal extends Model
{
    protected $fillable = ['meal_id', 'order_id', 'count'];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function meal()
    {
        return $this->belongsTo(Meal::class, 'meal_id');
    }
}
