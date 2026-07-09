<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ScheduledPost;
use App\Models\Schedule;
use App\Jobs\PublishPostJob;

$post = ScheduledPost::find(5);
if (!$post) { echo "Post not found\n"; exit(1); }

$post->update(['status' => 'Pending', 'failed_reason' => null]);
Schedule::where('scheduled_post_id', 5)->update(['status' => 'Pending', 'scheduled_at' => now()]);

echo "Running job synchronously for post ID 5: '{$post->title}' using the new OAuth 2.0 account...\n";
try {
    $job = new PublishPostJob($post);
    $job->handle();
    $post->refresh();
    echo "Status: " . $post->status . "\n";
    echo "Failed Reason: " . ($post->failed_reason ?? 'none') . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
