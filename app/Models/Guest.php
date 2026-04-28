<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_extra' => 'boolean',
        'amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function invitation()
    {
        return $this->hasOne(Invitation::class);
    }
}
