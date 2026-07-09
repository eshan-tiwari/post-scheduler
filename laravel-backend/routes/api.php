<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SocialAccountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check (public - no auth required)
Route::get('/test', function () {
    return response()->json([
        'status'  => 'ok',
        'message' => 'Laravel API is running',
        'time'    => now()->toDateTimeString(),
    ]);
});

// Auth Routes (Public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// OAuth Callback (Public)
Route::get('/social/callback/{platform}', [SocialAccountController::class, 'handleProviderCallback'])->name('social.callback');
Route::post('/social/callback/{platform}', [SocialAccountController::class, 'handleProviderCallback']);

// Protected Routes (Sanctum Auth)
Route::middleware('auth:sanctum')->group(function () {

    // Get logged-in user info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });

    // Social Accounts API
    Route::get('/social/accounts', [SocialAccountController::class, 'index']);
    Route::get('/social/connect/{platform}', [SocialAccountController::class, 'redirectToProvider']);
    Route::delete('/social/accounts/{id}', [SocialAccountController::class, 'destroy']);

    // Scheduled Posts API
    Route::get('/posts',         [PostController::class, 'index']);
    Route::post('/posts',        [PostController::class, 'store']);
    Route::get('/posts/{id}',    [PostController::class, 'show']);
    Route::put('/posts/{id}',    [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::post('/posts/{id}/retry', [PostController::class, 'retry']);

    // Dashboard Statistics API
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);

    // Publish Logs API
    Route::get('/publish-logs', [\App\Http\Controllers\PublishLogController::class, 'index']);

    // Platform Credentials API (user enters their own API keys)
    Route::get('/credentials',                    [\App\Http\Controllers\CredentialsController::class, 'index']);
    Route::post('/credentials/{platform}',        [\App\Http\Controllers\CredentialsController::class, 'store']);
    Route::post('/credentials/{platform}/verify', [\App\Http\Controllers\CredentialsController::class, 'verify']);
    Route::delete('/credentials/{platform}',      [\App\Http\Controllers\CredentialsController::class, 'destroy']);
});
