<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Schedule as PostSchedule;
use App\Jobs\PublishPostJob;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Check for pending scheduled posts and dispatch queue jobs.
 * Runs every minute in production.
 */
Schedule::call(function () {
    Log::info('Scheduler: Checking for pending posts to publish...');
    
    // Find schedules that are pending and due
    $dueSchedules = PostSchedule::where('status', 'Pending')
        ->where('scheduled_at', '<=', now())
        ->with('post')
        ->get();

    if ($dueSchedules->isEmpty()) {
        return;
    }

    Log::info("Scheduler: Found {$dueSchedules->count()} posts due for publishing.");

    foreach ($dueSchedules as $scheduleItem) {
        $post = $scheduleItem->post;
        
        if (!$post) {
            $scheduleItem->update(['status' => 'Failed']);
            continue;
        }

        // Set status to Executing so we don't double process
        $scheduleItem->update(['status' => 'Executing']);
        
        // Dispatch job to queue
        PublishPostJob::dispatch($post);
        Log::info("Scheduler: Dispatched PublishPostJob for post ID: {$post->id}");
    }
})->everyMinute();
