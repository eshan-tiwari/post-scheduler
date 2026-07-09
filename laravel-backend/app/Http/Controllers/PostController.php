<?php

namespace App\Http\Controllers;

use App\Models\ScheduledPost;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\PublishLog;
use App\Services\Social\SocialServiceResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    /**
     * GET /api/posts
     * Retrieve all scheduled posts for the user (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Support filtering by status or search keyword
        $query = ScheduledPost::where('user_id', $userId)
            ->with(['media', 'schedules'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->input('status') !== 'All') {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Support pagination
        $posts = $query->paginate($request->input('per_page', 15));

        return response()->json($posts, 200);
    }

    /**
     * POST /api/posts
     * Create a new scheduled post (multi-platform & multi-media).
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:100',
            'content'          => 'required|string',
            'platforms'        => 'required|array|min:1',
            'platforms.*'      => 'string|in:X/Twitter,Instagram,Facebook,LinkedIn,Bluesky,Reddit',
            'scheduled_at'     => 'required|date',
            'timezone'         => 'sometimes|string',
            'recurrence'       => 'sometimes|string|in:once,daily,weekly,monthly',
            'files'            => 'sometimes|array',
            'files.*'          => 'file|mimes:jpeg,jpg,png,gif,mp4,mov|max:10240', // max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Create the ScheduledPost record
        $post = ScheduledPost::create([
            'user_id'          => $userId,
            'title'            => $request->title,
            'content'          => $request->content,
            'platform'         => $request->platforms[0], // fallback for legacy column
            'platforms'        => $request->platforms,
            'scheduled_at'     => $request->scheduled_at,
            'timezone'         => $request->input('timezone', 'UTC'),
            'recurrence'       => $request->input('recurrence', 'once'),
            'status'           => 'Pending',
        ]);

        // Create the core Schedule entry
        Schedule::create([
            'scheduled_post_id' => $post->id,
            'scheduled_at'      => $request->scheduled_at,
            'status'            => 'Pending',
        ]);

        // Process attachments/files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('media', 'public');
                Media::create([
                    'scheduled_post_id' => $post->id,
                    'file_path'         => $path,
                    'file_type'         => str_contains($file->getMimeType(), 'video') ? 'video' : 'image',
                ]);
            }
        }

        // Return relation loaded post
        $post->load(['media', 'schedules']);

        return response()->json([
            'message' => 'Post scheduled successfully.',
            'post'    => $post,
        ], 201);
    }

    /**
     * GET /api/posts/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::where('user_id', $request->user()->id)
            ->with(['media', 'schedules', 'publishLogs'])
            ->find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        return response()->json($post, 200);
    }

    /**
     * PUT /api/posts/{id}
     * Update/Modify scheduled post metadata, or pause/resume/edit.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::where('user_id', $request->user()->id)->find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:100',
            'content'      => 'sometimes|string',
            'platforms'    => 'sometimes|array',
            'scheduled_at' => 'sometimes|date',
            'timezone'     => 'sometimes|string',
            'recurrence'   => 'sometimes|string|in:once,daily,weekly,monthly',
            'status'       => 'sometimes|string|in:Draft,Pending,Published,Failed,Paused',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Update fields
        $fields = $request->only(['title', 'content', 'platforms', 'scheduled_at', 'timezone', 'recurrence', 'status']);
        
        if (isset($fields['platforms']) && count($fields['platforms']) > 0) {
            $fields['platform'] = $fields['platforms'][0]; // legacy fallback
        }

        $post->update($fields);

        // Synchronize schedules if date updated
        if (isset($fields['scheduled_at'])) {
            Schedule::where('scheduled_post_id', $post->id)
                ->where('status', 'Pending')
                ->update(['scheduled_at' => $fields['scheduled_at']]);
        }

        // Handle pause/resume statuses
        if (isset($fields['status'])) {
            if ($fields['status'] === 'Paused') {
                Schedule::where('scheduled_post_id', $post->id)
                    ->where('status', 'Pending')
                    ->update(['status' => 'Paused']);
            } elseif ($fields['status'] === 'Pending') {
                Schedule::where('scheduled_post_id', $post->id)
                    ->where('status', 'Paused')
                    ->update(['status' => 'Pending']);
            }
        }

        return response()->json([
            'message' => 'Post updated successfully.',
            'post'    => $post->load(['media', 'schedules']),
        ], 200);
    }

    /**
     * DELETE /api/posts/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::where('user_id', $request->user()->id)->find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        // Clean up media files from storage
        foreach ($post->media ?? [] as $media) {
            Storage::disk('public')->delete($media->file_path);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ], 200);
    }

    /**
     * POST /api/posts/{id}/retry
     * Immediately triggers posting process or marks it for retry.
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::where('user_id', $request->user()->id)
            ->with(['media'])
            ->find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        // Reset status to Pending
        $post->update([
            'status' => 'Pending',
            'failed_reason' => null
        ]);

        // Reset schedule items to Pending
        Schedule::where('scheduled_post_id', $post->id)->update([
            'status' => 'Pending',
            'scheduled_at' => now() // run immediately
        ]);

        // Dispatch publishing job immediately
        try {
            // Run synchronous dispatch for test immediate response
            $job = new \App\Jobs\PublishPostJob($post);
            dispatch($job);

            return response()->json([
                'message' => 'Post retrying now. Check status in a few seconds.',
                'post' => $post->load(['media', 'schedules'])
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to queue post retry: " . $e->getMessage());
            return response()->json([
                'message' => 'Post marked for retry but worker failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
