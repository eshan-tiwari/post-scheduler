<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use App\Models\PublishLog;
use App\Models\Schedule;
use App\Services\Social\SocialServiceResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ScheduledPost $post;

    /**
     * Create a new job instance.
     */
    public function __construct(ScheduledPost $post)
    {
        $this->post = $post;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("PublishPostJob: Starting execution for post ID: {$this->post->id}");

        $platforms = $this->post->platforms;
        if (empty($platforms)) {
            // Fallback for legacy database records
            $platforms = [$this->post->platform];
        }

        $userId = $this->post->user_id;
        $successCount = 0;
        $failureReasons = [];

        // Set status of post to Publishing
        $this->post->update(['status' => 'Publishing']);

        foreach ($platforms as $platformName) {
            // Standardize platform name for matching
            $stdPlatform = strtolower($platformName);
            if ($stdPlatform === 'x/twitter' || $stdPlatform === 'x' || $stdPlatform === 'twitter') {
                $platformKey = 'twitter';
            } elseif ($stdPlatform === 'bluesky') {
                $platformKey = 'bluesky';
            } elseif ($stdPlatform === 'reddit') {
                $platformKey = 'reddit';
            } else {
                $platformKey = $stdPlatform;
            }

            // Find connected account (match 'twitter'/'X/Twitter' and support case-insensitive variants)
            $account = ConnectedAccount::where('user_id', $userId)
                ->where(function ($query) use ($platformKey, $platformName) {
                    $query->where('platform', $platformKey)
                          ->orWhere('platform', $platformName)
                          ->orWhereRaw('LOWER(platform) = ?', [strtolower($platformKey)])
                          ->orWhereRaw('LOWER(platform) = ?', [strtolower($platformName)]);
                })
                ->first();

            if (!$account) {
                $reason = "No connected account found for {$platformName}";
                Log::warning("PublishPostJob: {$reason}");
                
                PublishLog::create([
                    'scheduled_post_id' => $this->post->id,
                    'connected_account_id' => null,
                    'platform' => $platformName,
                    'status' => 'Failed',
                    'error_message' => $reason,
                ]);

                $failureReasons[] = $reason;
                continue;
            }

            try {
                // Call platform service
                $service = SocialServiceResolver::resolve($platformKey);
                $result = $service->publishPost($this->post, $account);

                if ($result['status'] === 'Success') {
                    PublishLog::create([
                        'scheduled_post_id' => $this->post->id,
                        'connected_account_id' => $account->id,
                        'platform' => $platformName,
                        'status' => 'Success',
                        'response_id' => $result['response_id'] ?? null,
                        'published_at' => now(),
                    ]);
                    $successCount++;
                } else {
                    $errorMsg = $result['error_message'] ?? 'Unknown platform publishing error';
                    PublishLog::create([
                        'scheduled_post_id' => $this->post->id,
                        'connected_account_id' => $account->id,
                        'platform' => $platformName,
                        'status' => 'Failed',
                        'error_message' => $errorMsg,
                    ]);
                    $failureReasons[] = "{$platformName}: {$errorMsg}";
                }
            } catch (\Exception $e) {
                $errorMsg = "API Exception: " . $e->getMessage();
                Log::error("PublishPostJob exception for {$platformName}: " . $e->getMessage());

                PublishLog::create([
                    'scheduled_post_id' => $this->post->id,
                    'connected_account_id' => $account->id,
                    'platform' => $platformName,
                    'status' => 'Failed',
                    'error_message' => $errorMsg,
                ]);
                $failureReasons[] = "{$platformName}: {$errorMsg}";
            }
        }

        // Determine overall post status
        $totalPlatforms = count($platforms);
        if ($successCount === $totalPlatforms) {
            $overallStatus = 'Published';
            $failedReason = null;
        } elseif ($successCount > 0) {
            $overallStatus = 'Partial';
            $failedReason = implode('; ', $failureReasons);
        } else {
            $overallStatus = 'Failed';
            $failedReason = implode('; ', $failureReasons);
        }

        // Handle recurrence scheduling if successful or partially successful
        if ($overallStatus !== 'Failed' && $this->post->recurrence !== 'once') {
            $nextRun = $this->calculateNextRunDate($this->post->scheduled_at, $this->post->recurrence);
            
            // Log schedule item as Completed
            Schedule::where('scheduled_post_id', $this->post->id)
                ->where('status', 'Pending')
                ->update([
                    'status' => 'Completed',
                    'last_run_at' => now(),
                    'run_count' => \DB::raw('run_count + 1')
                ]);

            // Create next schedule entry
            Schedule::create([
                'scheduled_post_id' => $this->post->id,
                'scheduled_at' => $nextRun,
                'status' => 'Pending'
            ]);

            // Keep post as Pending for next execution, but update scheduled_at time
            $this->post->update([
                'scheduled_at' => $nextRun,
                'status' => 'Pending',
                'failed_reason' => null
            ]);

            Log::info("PublishPostJob: Post ID {$this->post->id} rescheduled for {$nextRun} (recurrence: {$this->post->recurrence})");
        } else {
            // Update current schedule item to Completed or Failed
            Schedule::where('scheduled_post_id', $this->post->id)
                ->where('status', 'Pending')
                ->update([
                    'status' => $overallStatus === 'Published' ? 'Completed' : 'Failed',
                    'last_run_at' => now(),
                    'run_count' => \DB::raw('run_count + 1')
                ]);

            $this->post->update([
                'status' => $overallStatus,
                'failed_reason' => $failedReason
            ]);
        }
    }

    /**
     * Calculate next run date based on frequency.
     */
    protected function calculateNextRunDate(\Carbon\Carbon $currentDate, string $frequency): \Carbon\Carbon
    {
        $date = $currentDate->copy();
        
        switch ($frequency) {
            case 'daily':
                return $date->addDay();
            case 'weekly':
                return $date->addWeek();
            case 'monthly':
                return $date->addMonth();
            default:
                return $date->addDay();
        }
    }
}
