<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    protected $fillable = ['name','company_id','date'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function cardmeals()
    {
        return $this->hasMany(CardMeal::class, 'card_id');
    }
}
