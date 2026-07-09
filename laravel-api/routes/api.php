<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth Routes (Public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Protected Routes (Sanctum Auth)
Route::middleware('auth:sanctum')->group(function () {

    // Get logged in user
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Scheduled Posts API
    Route::get('/posts',          [PostController::class, 'index']);
    Route::post('/posts',         [PostController::class, 'store']);
    Route::get('/posts/{id}',     [PostController::class, 'show']);
    Route::put('/posts/{id}',     [PostController::class, 'update']);
    Route::delete('/posts/{id}',  [PostController::class, 'destroy']);
});
