<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Models\ScheduledPost;
use App\Models\PublishLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Fetch aggregated statistics for the dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Connected Account Count
        $connectedAccountsCount = ConnectedAccount::where('user_id', $userId)->count();

        // Platform-wise breakdown
        $platformsBreakdown = ConnectedAccount::where('user_id', $userId)
            ->select('platform', DB::raw('count(*) as count'))
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        // Post counts
        $totalScheduled = ScheduledPost::where('user_id', $userId)->count();
        $pendingPostsCount = ScheduledPost::where('user_id', $userId)->where('status', 'Pending')->count();
        $publishedPostsCount = ScheduledPost::where('user_id', $userId)->where('status', 'Published')->count();
        $failedPostsCount = ScheduledPost::where('user_id', $userId)->where('status', 'Failed')->count();

        // Success Rate from logs
        $totalLogsCount = PublishLog::whereHas('post', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->count();

        $successLogsCount = PublishLog::whereHas('post', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->where('status', 'Success')->count();

        $successRate = $totalLogsCount > 0 ? round(($successLogsCount / $totalLogsCount) * 100, 1) : 100.0;

        // Fetch monthly posting activity graph (last 6 months)
        $activityData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthName = $date->format('M Y');
            
            $successCount = PublishLog::whereHas('post', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->where('status', 'Success')
              ->whereYear('published_at', $date->year)
              ->whereMonth('published_at', $date->month)
              ->count();

            $failedCount = PublishLog::whereHas('post', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->where('status', 'Failed')
              ->whereYear('created_at', $date->year) // fall back to log creation if not published
              ->whereMonth('created_at', $date->month)
              ->count();

            $activityData[] = [
                'month' => $monthName,
                'published' => $successCount,
                'failed' => $failedCount,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'connected_accounts' => $connectedAccountsCount,
                'platforms' => $platformsBreakdown,
                'posts' => [
                    'total' => $totalScheduled,
                    'pending' => $pendingPostsCount,
                    'published' => $publishedPostsCount,
                    'failed' => $failedPostsCount,
                ],
                'success_rate' => $successRate,
                'activity_graph' => $activityData
            ]
        ]);
    }
}
