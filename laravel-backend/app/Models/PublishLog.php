<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'connected_account_id',
        'platform',
        'status',
        'response_id',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }
}
