<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'scheduled_at',
        'status',
        'run_count',
        'last_run_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }
}
