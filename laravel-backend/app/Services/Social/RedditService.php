<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedditService implements PlatformServiceInterface
{
    private const TOKEN_URL  = 'https://www.reddit.com/api/v1/access_token';
    private const SUBMIT_URL = 'https://oauth.reddit.com/api/submit';

    public function getAuthUrl(int $userId): string { return ''; }
    public function handleCallback(array $data): array { return []; }

    /**
     * Authenticate with Reddit using Script-type OAuth2 (username + password flow).
     * Returns access_token + username.
     */
    public static function authenticate(string $clientId, string $clientSecret, string $username, string $password): array
    {
        $response = Http::withoutVerifying()
            ->timeout(3)
            ->withBasicAuth($clientId, $clientSecret)
            ->withHeaders([
                'User-Agent' => 'PostScheduler:v1.0 (by /u/' . $username . ')',
            ])
            ->asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'password',
                'username'   => $username,
                'password'   => $password,
            ]);


        if ($response->failed()) {
            $error = $response->json()['message'] ?? $response->body();
            throw new \Exception('Reddit auth failed: ' . $error);
        }

        $data = $response->json();

        if (!isset($data['access_token'])) {
            $error = $data['error'] ?? json_encode($data);
            throw new \Exception('Reddit auth error: ' . $error);
        }

        return [
            'access_token' => $data['access_token'],
            'username'     => $username,
        ];
    }

    /**
     * Publish a post to Reddit.
     */
    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("RedditService: Publishing post ID {$post->id}");

        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'reddit')
            ->first();

        if (!$cred) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Reddit credentials not found. Please connect in the Connect Platforms page.',
            ];
        }

        // Mock mode support
        if ($cred->api_key === 'mock' || str_starts_with($cred->api_key ?? '', 'mock_')) {
            Log::info("Reddit mock post published successfully.");
            return [
                'status'      => 'Success',
                'response_id' => 'mock_reddit_' . time(),
            ];
        }

        $clientId     = $cred->api_key;             // Client ID
        $clientSecret = $cred->api_secret;          // Client Secret
        $username     = $cred->access_token;        // Reddit Username
        $password     = $cred->access_token_secret; // Reddit Password
        $subreddit    = $cred->page_id;             // Target subreddit name

        if (empty($subreddit)) {
            $subreddit = 'test'; // fallback to r/test
        }

        try {
            // Step 1: Get access token
            $auth = self::authenticate($clientId, $clientSecret, $username, $password);
            $token = $auth['access_token'];

            // Step 2: Build post title (use post title or truncate content)
            $title = $post->title ?? substr($post->content, 0, 100);

            // Step 3: Submit post
            $response = Http::withoutVerifying()
                ->timeout(3)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'User-Agent'    => 'PostScheduler:v1.0 (by /u/' . $username . ')',
                ])
                ->asForm()
                ->post(self::SUBMIT_URL, [
                    'sr'      => $subreddit,
                    'kind'    => 'self',
                    'title'   => $title,
                    'text'    => $post->content,
                    'resubmit'=> 'true',
                    'nsfw'    => 'false',
                ]);


            Log::info("RedditService response: " . $response->status() . " " . $response->body());

            if ($response->successful()) {
                $json = $response->json();
                // Check for Reddit API-level errors
                $errors = $json['json']['errors'] ?? [];
                if (!empty($errors)) {
                    $errMsg = collect($errors)->map(fn($e) => implode(': ', $e))->implode(', ');
                    return ['status' => 'Failed', 'error_message' => 'Reddit error: ' . $errMsg];
                }
                $postUrl = $json['json']['data']['url'] ?? null;
                Log::info("Reddit post published. URL: {$postUrl}");
                return [
                    'status'      => 'Success',
                    'response_id' => $postUrl,
                ];
            }

            $error = $response->json()['message'] ?? $response->body();
            return [
                'status'        => 'Failed',
                'error_message' => 'Reddit API Error: ' . $error,
            ];

        } catch (\Exception $e) {
            Log::error("RedditService exception: " . $e->getMessage());
            return [
                'status'        => 'Failed',
                'error_message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }
}
