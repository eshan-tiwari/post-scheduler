<?php

namespace App\Http\Controllers;

use App\Models\PlatformCredential;
use App\Services\Social\CredentialVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CredentialsController extends Controller
{
    /**
     * Get all credential configs for this user (values masked for security).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $platforms = ['twitter', 'bluesky', 'reddit', 'instagram', 'facebook', 'linkedin'];

        $result = [];

        foreach ($platforms as $platform) {
            $cred = PlatformCredential::where('user_id', $userId)
                ->where('platform', $platform)
                ->first();

            $result[$platform] = [
                'platform'          => $platform,
                'is_configured'     => !is_null($cred),
                'is_verified'       => $cred?->is_verified ?? false,
                'connected_username'=> $cred?->connected_username,
                'last_verified_at'  => $cred?->last_verified_at,
                // Show masked values only if already set
                'has_api_key'              => !empty($cred?->api_key),
                'has_api_secret'           => !empty($cred?->api_secret),
                'has_access_token'         => !empty($cred?->access_token),
                'has_access_token_secret'  => !empty($cred?->access_token_secret),
                'has_bearer_token'         => !empty($cred?->bearer_token),
                'has_page_access_token'    => !empty($cred?->page_access_token),
                'page_id'                  => $cred?->page_id,
                'li_person_urn'            => $cred?->li_person_urn,
            ];
        }

        return response()->json(['status' => 'success', 'credentials' => $result]);
    }

    /**
     * Save (create or update) credentials for a platform.
     */
    public function store(Request $request, string $platform): JsonResponse
    {
        $platform = strtolower($platform);
        $userId   = $request->user()->id;

        $allowedPlatforms = ['twitter', 'instagram', 'facebook', 'linkedin', 'bluesky', 'reddit'];
        if (!in_array($platform, $allowedPlatforms)) {
            return response()->json(['message' => 'Unsupported platform.'], 400);
        }

        // Platform-specific validation rules
        $rules = $this->getValidationRules($platform);

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Build fields to save based on platform
        $fields = $this->extractFields($platform, $request);
        $fields['user_id']      = $userId;
        $fields['platform']     = $platform;
        $fields['is_verified']  = false; // Reset verification on save
        $fields['connected_username'] = null;

        // Upsert credentials
        $cred = PlatformCredential::updateOrCreate(
            ['user_id' => $userId, 'platform' => $platform],
            $fields
        );

        return response()->json([
            'status'  => 'success',
            'message' => ucfirst($platform) . ' credentials saved. Click "Verify & Connect" to test them.',
            'platform' => $platform,
        ]);
    }

    /**
     * Verify stored credentials by making a test API call.
     */
    public function verify(Request $request, string $platform): JsonResponse
    {
        $platform = strtolower($platform);
        $userId   = $request->user()->id;

        $cred = PlatformCredential::where('user_id', $userId)->where('platform', $platform)->first();
        if (!$cred) {
            return response()->json([
                'message' => 'No credentials saved for ' . ucfirst($platform) . '. Please save credentials first.',
            ], 404);
        }

        try {
            $verifier = new CredentialVerifier();
            $result   = $verifier->verify($platform, $cred);

            if ($result['success']) {
                $cred->update([
                    'is_verified'        => true,
                    'connected_username' => $result['username'],
                    'last_verified_at'   => now(),
                ]);

                // Map platform key to match creation form keys (e.g. X/Twitter, Instagram, Facebook, LinkedIn)
                $connectedPlatformName = match ($platform) {
                    'twitter'   => 'X/Twitter',
                    'instagram' => 'Instagram',
                    'facebook'  => 'Facebook',
                    'linkedin'  => 'LinkedIn',
                    'bluesky'   => 'Bluesky',
                    'reddit'    => 'Reddit',
                    default     => ucfirst($platform)
                };

                // Create or update record in connected_accounts
                \App\Models\ConnectedAccount::updateOrCreate(
                    [
                        'user_id'  => $userId,
                        'platform' => $connectedPlatformName,
                    ],
                    [
                        'platform_user_id' => $result['platform_user_id'] ?? $platform . '_user',
                        'username'         => $result['username'],
                        'avatar_url'       => 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($result['username']),
                        'access_token'     => 'credentials_based', // post jobs read PlatformCredential directly
                        'refresh_token'    => null,
                        'expires_at'       => null,
                    ]
                );

                return response()->json([
                    'status'   => 'success',
                    'message'  => '✅ Successfully connected as @' . $result['username'],
                    'username' => $result['username'],
                ]);
            } else {
                $cred->update(['is_verified' => false, 'connected_username' => null]);
                
                $connectedPlatformName = match ($platform) {
                    'twitter'   => 'X/Twitter',
                    'instagram' => 'Instagram',
                    'facebook'  => 'Facebook',
                    'linkedin'  => 'LinkedIn',
                    default     => ucfirst($platform)
                };
                \App\Models\ConnectedAccount::where('user_id', $userId)->where('platform', $connectedPlatformName)->delete();

                return response()->json([
                    'status'  => 'error',
                    'message' => '❌ Verification failed: ' . $result['error'],
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => '❌ Error testing credentials: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete (clear) credentials for a platform.
     */
    public function destroy(Request $request, string $platform): JsonResponse
    {
        $platform = strtolower($platform);
        $userId   = $request->user()->id;

        PlatformCredential::where('user_id', $userId)->where('platform', $platform)->delete();

        $connectedPlatformName = match ($platform) {
            'twitter'   => 'X/Twitter',
            'instagram' => 'Instagram',
            'facebook'  => 'Facebook',
            'linkedin'  => 'LinkedIn',
            default     => ucfirst($platform)
        };
        \App\Models\ConnectedAccount::where('user_id', $userId)->where('platform', $connectedPlatformName)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => ucfirst($platform) . ' credentials removed.',
        ]);
    }

    private function getValidationRules(string $platform): array
    {
        return match ($platform) {
            'twitter' => [
                'api_key'             => 'required|string',
                'api_secret'          => 'required|string',
                'access_token'        => 'required|string',
                'access_token_secret' => 'required|string',
            ],
            'instagram' => [
                'page_access_token' => 'required|string',
                'page_id'           => 'required|string',
            ],
            'facebook' => [
                'page_access_token' => 'required|string',
                'page_id'           => 'required|string',
            ],
            'linkedin' => [
                'li_access_token' => 'required|string',
                'li_person_urn'   => 'required|string',
            ],
            'bluesky' => [
                'api_key'    => 'required|string', // handle
                'api_secret' => 'required|string', // app password
            ],
            'reddit' => [
                'api_key'             => 'required|string', // client_id
                'api_secret'          => 'required|string', // client_secret
                'access_token'        => 'required|string', // username
                'access_token_secret' => 'required|string', // password
                'page_id'             => 'required|string', // subreddit
            ],
            default => [],
        };
    }

    private function extractFields(string $platform, Request $request): array
    {
        return match ($platform) {
            'twitter' => [
                'api_key'             => $request->api_key,
                'api_secret'          => $request->api_secret,
                'access_token'        => $request->access_token,
                'access_token_secret' => $request->access_token_secret,
            ],
            'instagram', 'facebook' => [
                'page_access_token' => $request->page_access_token,
                'page_id'           => $request->page_id,
            ],
            'linkedin' => [
                'li_access_token' => $request->li_access_token,
                'li_person_urn'   => $request->li_person_urn,
            ],
            'bluesky' => [
                'api_key'    => $request->api_key,    // handle
                'api_secret' => $request->api_secret, // app password
            ],
            'reddit' => [
                'api_key'             => $request->api_key,             // client_id
                'api_secret'          => $request->api_secret,          // client_secret
                'access_token'        => $request->access_token,        // username
                'access_token_secret' => $request->access_token_secret, // password
                'page_id'             => $request->page_id,             // subreddit
            ],
            default => [],
        };
    }
}
