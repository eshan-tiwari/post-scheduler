<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use App\Services\Social\CredentialVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitterService implements PlatformServiceInterface
{
    public function getAuthUrl(int $userId): string
    {
        $state = encrypt(['user_id' => $userId]);
        $clientId = env('TWITTER_CLIENT_ID');

        if (empty($clientId) || $clientId === 'mock' || $clientId === 'test') {
            Log::info("TwitterService: Using mock OAuth redirect flow");
            // Redirect directly to backend callback route
            return route('social.callback', ['platform' => 'twitter']) . '?' . http_build_query([
                'code' => 'mock_auth_code',
                'state' => $state,
            ]);
        }

        $codeVerifier = \Illuminate\Support\Str::random(40);
        \Illuminate\Support\Facades\Cache::put('twitter_code_verifier_' . $state, $codeVerifier, now()->addMinutes(15));

        $redirectUri = env('TWITTER_REDIRECT_URI', 'http://127.0.0.1:8000/api/social/callback/twitter');
        $query = http_build_query([
            'response_type'         => 'code',
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'scope'                 => 'tweet.read tweet.write users.read offline.access',
            'state'                 => $state,
            'code_challenge'        => $codeVerifier,
            'code_challenge_method' => 'plain',
        ]);

        return 'https://twitter.com/i/oauth2/authorize?' . $query;
    }

    public function handleCallback(array $data): array
    {
        $code = $data['code'] ?? '';
        $state = $data['state'] ?? '';

        if ($code === 'mock_auth_code') {
            return [
                'platform_user_id' => 'mock_twitter_123',
                'username'         => 'mock_twitter_user',
                'avatar_url'       => 'https://api.dicebear.com/7.x/bottts/svg?seed=mock_twitter_user',
                'access_token'     => 'mock_access_token',
                'refresh_token'    => 'mock_refresh_token',
                'expires_at'       => now()->addHours(2),
            ];
        }

        $clientId = env('TWITTER_CLIENT_ID');
        $clientSecret = env('TWITTER_CLIENT_SECRET');
        $redirectUri = env('TWITTER_REDIRECT_URI', 'http://127.0.0.1:8000/api/social/callback/twitter');
        $codeVerifier = \Illuminate\Support\Facades\Cache::pull('twitter_code_verifier_' . $state);

        if (!$codeVerifier) {
            throw new \Exception('OAuth state expired or invalid. Please try again.');
        }

        // Exchange Authorization Code for Access Token
        $response = Http::withoutVerifying()
            ->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://api.twitter.com/2/oauth2/token', [
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirectUri,
                'code_verifier' => $codeVerifier,
            ]);

        if ($response->failed()) {
            $error = $response->json()['error_description'] ?? $response->json()['error'] ?? $response->body();
            throw new \Exception('Failed to exchange Twitter authorization code: ' . $error);
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 7200;

        // Fetch User Profile Details from Twitter API
        $userResponse = Http::withoutVerifying()
            ->withToken($accessToken)
            ->get('https://api.twitter.com/2/users/me', [
                'user.fields' => 'profile_image_url,username',
            ]);

        if ($userResponse->failed()) {
            throw new \Exception('Failed to retrieve Twitter user profile: ' . $userResponse->body());
        }

        $userData = $userResponse->json()['data'] ?? [];
        $username = $userData['username'] ?? 'TwitterUser';
        $userId = $userData['id'] ?? 'twitter_user';
        $avatarUrl = $userData['profile_image_url'] ?? 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($username);

        return [
            'platform_user_id' => $userId,
            'username'         => $username,
            'avatar_url'       => $avatarUrl,
            'access_token'     => $accessToken,
            'refresh_token'    => $refreshToken,
            'expires_at'       => now()->addSeconds($expiresIn),
        ];
    }

    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("TwitterService: Publishing post ID {$post->id}");

        // If the account was connected via OAuth 2.0 (we have access_token directly on account)
        if ($account->access_token && $account->access_token !== 'credentials_based') {
            try {
                $token = $account->access_token;

                // Handle mock OAuth access token
                if ($token === 'mock_access_token' || str_starts_with($token, 'mock_')) {
                    Log::info("Twitter mock OAuth post published successfully.");
                    return [
                        'status'      => 'Success',
                        'response_id' => 'mock_oauth_tweet_' . time(),
                    ];
                }

                // Auto-refresh token if expired (or close to expiring)
                if ($account->expires_at && $account->expires_at->isPast() && $account->refresh_token) {
                    $token = $this->refreshOAuthToken($account);
                }

                $response = Http::withoutVerifying()->withToken($token)
                    ->post('https://api.twitter.com/2/tweets', [
                        'text' => $post->content
                    ]);

                if ($response->successful()) {
                    $tweetId = $response->json()['data']['id'] ?? null;
                    return [
                        'status'      => 'Success',
                        'response_id' => $tweetId,
                    ];
                }

                // If token expired (401) and we haven't refreshed yet
                if ($response->status() === 401 && $account->refresh_token) {
                    $token = $this->refreshOAuthToken($account);
                    $response = Http::withoutVerifying()->withToken($token)
                        ->post('https://api.twitter.com/2/tweets', [
                            'text' => $post->content
                        ]);

                    if ($response->successful()) {
                        $tweetId = $response->json()['data']['id'] ?? null;
                        return [
                            'status'      => 'Success',
                            'response_id' => $tweetId,
                        ];
                    }
                }

                $error = $response->json()['detail'] ?? $response->json()['title'] ?? $response->body();
                return [
                    'status'        => 'Failed',
                    'error_message' => 'Twitter API v2 Error: ' . $error,
                ];
            } catch (\Exception $e) {
                return [
                    'status'        => 'Failed',
                    'error_message' => 'OAuth2 Publish Exception: ' . $e->getMessage(),
                ];
            }
        }

        // Load credentials from DB (Legacy OAuth 1.0a Flow)
        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'twitter')
            ->first();

        if (!$cred) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Twitter credentials not found. Please go to Connect and re-enter your keys.',
            ];
        }

        try {
            if ($cred->api_key === 'mock' || str_starts_with($cred->api_key, 'mock_') || $cred->api_key === 'test' || str_starts_with($cred->api_key, 'test_')) {
                Log::info("Twitter mock post published successfully.");
                return [
                    'status'      => 'Success',
                    'response_id' => 'mock_tweet_' . time(),
                ];
            }

            $verifier = new CredentialVerifier();

            // --- Attempt 1: POST /2/tweets (Twitter API v2) with OAuth 1.0a ---
            $urlV2     = 'https://api.twitter.com/2/tweets';
            $payloadV2 = ['text' => $post->content];
            $headerV2  = $verifier->buildTwitterOAuth1Header(
                method:            'POST',
                url:               $urlV2,
                params:            [],
                apiKey:            $cred->api_key,
                apiSecret:         $cred->api_secret,
                accessToken:       $cred->access_token,
                accessTokenSecret: $cred->access_token_secret
            );

            Log::info("TwitterService: Trying v2 POST /2/tweets with OAuth 1.0a");
            $responseV2 = Http::withoutVerifying()->withHeaders([
                'Authorization' => $headerV2,
                'Content-Type'  => 'application/json',
            ])->post($urlV2, $payloadV2);

            Log::info("TwitterService v2 response status: " . $responseV2->status() . " body: " . $responseV2->body());

            if ($responseV2->successful()) {
                $tweetId = $responseV2->json()['data']['id'] ?? null;
                Log::info("Twitter v2 post published. Tweet ID: {$tweetId}");
                return [
                    'status'      => 'Success',
                    'response_id' => $tweetId,
                ];
            }

            // --- Attempt 2: POST statuses/update (Twitter API v1.1) as fallback ---
            // Free-tier apps on Pay-Per-Use can sometimes still post via v1.1
            $urlV1      = 'https://api.twitter.com/1.1/statuses/update.json';
            $paramsV1   = ['status' => $post->content];
            $headerV1   = $verifier->buildTwitterOAuth1Header(
                method:            'POST',
                url:               $urlV1,
                params:            $paramsV1,
                apiKey:            $cred->api_key,
                apiSecret:         $cred->api_secret,
                accessToken:       $cred->access_token,
                accessTokenSecret: $cred->access_token_secret
            );

            Log::info("TwitterService: Trying v1.1 statuses/update with OAuth 1.0a");
            $responseV1 = Http::withoutVerifying()->withHeaders([
                'Authorization' => $headerV1,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->post($urlV1, $paramsV1);

            Log::info("TwitterService v1.1 response status: " . $responseV1->status() . " body: " . $responseV1->body());

            if ($responseV1->successful()) {
                $tweetId = $responseV1->json()['id_str'] ?? $responseV1->json()['id'] ?? null;
                Log::info("Twitter v1.1 post published. Tweet ID: {$tweetId}");
                return [
                    'status'      => 'Success',
                    'response_id' => $tweetId,
                ];
            }

            // Both attempts failed — return the v2 error as primary
            $errorV2 = $responseV2->json()['detail'] ?? $responseV2->json()['title'] ?? $responseV2->body();
            $errorV1 = $responseV1->json()['errors'][0]['message'] ?? $responseV1->body();
            Log::error("Twitter v2 error: {$errorV2} | v1.1 error: {$errorV1}");
            return [
                'status'        => 'Failed',
                'error_message' => 'Twitter API Error: ' . $errorV2 . ' (v1.1 fallback: ' . $errorV1 . ')',
            ];

        } catch (\Exception $e) {
            Log::error("TwitterService exception: " . $e->getMessage());
            return [
                'status'        => 'Failed',
                'error_message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    protected function refreshOAuthToken(ConnectedAccount $account): string
    {
        $clientId = env('TWITTER_CLIENT_ID');
        $clientSecret = env('TWITTER_CLIENT_SECRET');

        $response = Http::withoutVerifying()
            ->asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post('https://api.twitter.com/2/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to refresh Twitter OAuth token: ' . $response->body());
        }

        $tokenData = $response->json();
        $account->update([
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $account->refresh_token,
            'expires_at'    => now()->addSeconds($tokenData['expires_in'] ?? 7200),
        ]);

        return $tokenData['access_token'];
    }
}
