<?php

namespace App\Console\Commands;

use App\Models\ScheduledPost;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish all scheduled posts whose scheduled_at time has passed (Pending → Published).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking for posts ready to publish...');

        $now = Carbon::now();

        $pendingPosts = ScheduledPost::where('status', 'Pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($pendingPosts->isEmpty()) {
            $this->info('✅ No posts ready to publish at this time.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($pendingPosts as $post) {
            $post->update(['status' => 'Published']);
            $this->line("  📢 Published: [{$post->id}] \"{$post->title}\" on {$post->platform}");
            $count++;
        }

        $this->info("✅ Done! Published {$count} post(s).");

        return Command::SUCCESS;
    }
}
