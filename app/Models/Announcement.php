<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'date',
        'is_featured' => 'boolean',
    ];
}
