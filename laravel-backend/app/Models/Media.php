<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'file_path',
        'file_type',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }
}
