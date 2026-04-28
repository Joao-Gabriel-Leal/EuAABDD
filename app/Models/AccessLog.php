<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
    ];
}
