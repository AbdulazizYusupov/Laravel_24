<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'long',
        'lang',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public function card()
    {
        return $this->hasOne(Card::class, 'company_id');
    }
}
