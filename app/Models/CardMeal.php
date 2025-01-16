<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardMeal extends Model
{
    protected $fillable = [
        'card_id', 'meal_id',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}
