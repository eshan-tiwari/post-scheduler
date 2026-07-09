<?php

namespace App\Http\Controllers;

use App\Models\ScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    /**
     * GET /api/posts
     * Retrieve all scheduled posts (newest first).
     */
    public function index(): JsonResponse
    {
        $posts = ScheduledPost::orderBy('created_at', 'desc')->get();
        return response()->json($posts, 200);
    }

    /**
     * POST /api/posts
     * Create a new scheduled post.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:100',
            'content'      => 'required|string|max:280',
            'platform'     => 'required|string|in:X/Twitter,Instagram,Facebook,LinkedIn,YouTube',
            'scheduled_at' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $post = ScheduledPost::create([
            'title'        => $request->title,
            'content'      => $request->content,
            'platform'     => $request->platform,
            'scheduled_at' => $request->scheduled_at,
            'status'       => 'Pending',
        ]);

        return response()->json([
            'message' => 'Post scheduled successfully.',
            'post'    => $post,
        ], 201);
    }

    /**
     * GET /api/posts/{id}
     * Retrieve a specific scheduled post.
     */
    public function show(int $id): JsonResponse
    {
        $post = ScheduledPost::find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        return response()->json($post, 200);
    }

    /**
     * PUT /api/posts/{id}
     * Update a scheduled post (also used to simulate publishing).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:100',
            'content'      => 'sometimes|string|max:280',
            'platform'     => 'sometimes|string|in:X/Twitter,Instagram,Facebook,LinkedIn,YouTube',
            'scheduled_at' => 'sometimes|date',
            'status'       => 'sometimes|in:Pending,Published',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $post->update($request->only(['title', 'content', 'platform', 'scheduled_at', 'status']));

        return response()->json([
            'message' => 'Post updated successfully.',
            'post'    => $post,
        ], 200);
    }

    /**
     * DELETE /api/posts/{id}
     * Delete a scheduled post.
     */
    public function destroy(int $id): JsonResponse
    {
        $post = ScheduledPost::find($id);

        if (!$post) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        $post->delete();

        return response()->json([
            'message' => 'Post deleted successfully.',
        ], 200);
    }
}
