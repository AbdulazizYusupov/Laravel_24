<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    protected $fillable = ['name', 'category_id', 'price', 'image'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function cardmeals()
    {
        return $this->hasMany(CardMeal::class, 'meal_id');
    }
}
