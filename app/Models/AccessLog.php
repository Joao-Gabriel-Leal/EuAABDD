<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function dependent()
    {
        return $this->belongsTo(Dependent::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }
}
