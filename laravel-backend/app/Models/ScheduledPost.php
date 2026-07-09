<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledPost extends Model
{
    use HasFactory;

    protected $table = 'scheduled_posts';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'platform',          // Deprecating single platform in favor of platforms list, but keeping for compatibility
        'platforms',         // JSON list of platforms (e.g. ['Instagram', 'LinkedIn'])
        'scheduled_at',
        'timezone',
        'recurrence',        // once, daily, weekly, monthly
        'recurrence_rules',  // JSON config details
        'status',            // Draft, Pending, Published, Failed, Partial
        'failed_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'platforms' => 'array',
        'recurrence_rules' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'scheduled_post_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'scheduled_post_id');
    }

    public function publishLogs(): HasMany
    {
        return $this->hasMany(PublishLog::class, 'scheduled_post_id');
    }
}
