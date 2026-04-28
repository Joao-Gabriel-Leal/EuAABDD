<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_for' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
