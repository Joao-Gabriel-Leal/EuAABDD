<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
