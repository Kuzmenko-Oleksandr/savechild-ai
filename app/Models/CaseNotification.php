<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNotification extends Model
{
    protected $table = 'case_notifications';

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const STATUSES = ['check_it_out', 'in_progress', 'resolved'];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
