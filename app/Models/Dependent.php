<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dependent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'birthdate' => 'date',
        'is_free' => 'boolean',
        'monthly_fee' => 'decimal:2',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
