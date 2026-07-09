<?php

namespace App\Http\Controllers;

use App\Models\PublishLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishLogController extends Controller
{
    /**
     * Retrieve list of publish logs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $logs = PublishLog::whereHas('post', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with(['post:id,title,content', 'connectedAccount:id,username,platform'])
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        return response()->json($logs);
    }
}
