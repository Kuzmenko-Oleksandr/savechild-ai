<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
