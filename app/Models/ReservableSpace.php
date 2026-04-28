<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservableSpace extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
