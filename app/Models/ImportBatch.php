<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'finished_at' => 'datetime',
    ];

    public function rows()
    {
        return $this->hasMany(ImportRow::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
