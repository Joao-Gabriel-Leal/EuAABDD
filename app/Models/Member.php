<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'date',
        'address' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function dependents()
    {
        return $this->hasMany(Dependent::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
}
