<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dependent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'birthdate' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
