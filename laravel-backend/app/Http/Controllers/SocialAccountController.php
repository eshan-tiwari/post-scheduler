<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Services\Social\SocialServiceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SocialAccountController extends Controller
{
    /**
     * Get list of connected accounts for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->connectedAccounts ?? ConnectedAccount::where('user_id', $request->user()->id)->get();
        
        return response()->json([
            'status' => 'success',
            'accounts' => $accounts
        ]);
    }

    /**
     * Get redirect URL for OAuth provider.
     */
    public function redirectToProvider(Request $request, string $platform): JsonResponse
    {
        try {
            $service = SocialServiceResolver::resolve($platform);
            $url = $service->getAuthUrl($request->user()->id);

            return response()->json([
                'status' => 'success',
                'url' => $url
            ]);
        } catch (\Exception $e) {
            Log::error("OAuth redirect error for {$platform}: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle OAuth callback from provider.
     */
    public function handleProviderCallback(Request $request, string $platform): RedirectResponse
    {
        try {
            $service = SocialServiceResolver::resolve($platform);
            $profile = $service->handleCallback($request->all());

            // Resolve User ID from state or authenticate
            $stateData = [];
            if ($request->has('state')) {
                try {
                    $stateData = decrypt($request->input('state'));
                } catch (\Exception $e) {
                    Log::warning("Could not decrypt OAuth state: " . $e->getMessage());
                }
            }
            
            $userId = $stateData['user_id'] ?? $request->user()?->id;

            if (!$userId) {
                // Failback to first user in system if offline/unauthenticated callback
                $userId = \App\Models\User::first()?->id ?? 1;
            }

            // Save or Update connected account
            $account = ConnectedAccount::updateOrCreate(
                [
                    'user_id' => $userId,
                    'platform' => $platform,
                    'platform_user_id' => $profile['platform_user_id'],
                ],
                [
                    'username' => $profile['username'],
                    'avatar_url' => $profile['avatar_url'],
                    'access_token' => $profile['access_token'],
                    'refresh_token' => $profile['refresh_token'] ?? null,
                    'expires_at' => $profile['expires_at'] ?? null,
                ]
            );

            Log::info("Social account connected successfully: {$platform} for user {$userId}");

            // Redirect back to frontend
            return redirect()->away('http://localhost:4200/dashboard?connected=1&platform=' . $platform);

        } catch (\Exception $e) {
            Log::error("OAuth callback error for {$platform}: " . $e->getMessage());
            return redirect()->away('http://localhost:4200/dashboard?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Disconnect/Remove connected account.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $account = ConnectedAccount::where('user_id', $request->user()->id)->find($id);

        if (!$account) {
            return response()->json([
                'status' => 'error',
                'message' => 'Social account not found.'
            ], 404);
        }

        $account->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Social account disconnected successfully.'
        ]);
    }
}
