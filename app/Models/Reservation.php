<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reservation_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function space()
    {
        return $this->belongsTo(ReservableSpace::class, 'reservable_space_id');
    }

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
