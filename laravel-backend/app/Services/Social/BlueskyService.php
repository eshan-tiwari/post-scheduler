<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueskyService implements PlatformServiceInterface
{
    private const PDS_HOST = 'https://bsky.social';

    public function getAuthUrl(int $userId): string { return ''; }
    public function handleCallback(array $data): array { return []; }

    /**
     * Create a Bluesky session (login) and return the accessJwt + DID.
     */
    public static function createSession(string $handle, string $appPassword): array
    {
        $response = Http::withoutVerifying()
            ->post(self::PDS_HOST . '/xrpc/com.atproto.server.createSession', [
                'identifier' => $handle,
                'password'   => $appPassword,
            ]);

        if ($response->failed()) {
            $error = $response->json()['message'] ?? $response->body();
            throw new \Exception('Bluesky auth failed: ' . $error);
        }

        $data = $response->json();
        return [
            'accessJwt'  => $data['accessJwt'],
            'did'        => $data['did'],
            'handle'     => $data['handle'],
        ];
    }

    /**
     * Publish a post to Bluesky.
     */
    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("BlueskyService: Publishing post ID {$post->id}");

        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'bluesky')
            ->first();

        if (!$cred) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Bluesky credentials not found. Please connect in the Connect Platforms page.',
            ];
        }

        // Mock mode support
        if ($cred->api_key === 'mock' || str_starts_with($cred->api_key ?? '', 'mock_')) {
            Log::info("Bluesky mock post published successfully.");
            return [
                'status'      => 'Success',
                'response_id' => 'mock_bsky_' . time(),
            ];
        }

        $handle      = $cred->api_key;       // stored in api_key column
        $appPassword = $cred->api_secret;    // stored in api_secret column

        try {
            // Step 1: Authenticate
            $session = self::createSession($handle, $appPassword);
            $accessJwt = $session['accessJwt'];
            $did       = $session['did'];

            // Step 2: Create post record
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . $accessJwt])
                ->post(self::PDS_HOST . '/xrpc/com.atproto.repo.createRecord', [
                    'repo'       => $did,
                    'collection' => 'app.bsky.feed.post',
                    'record'     => [
                        '$type'     => 'app.bsky.feed.post',
                        'text'      => $post->content,
                        'createdAt' => now()->toIso8601String(),
                    ],
                ]);

            Log::info("BlueskyService response: " . $response->status() . " " . $response->body());

            if ($response->successful()) {
                $uri = $response->json()['uri'] ?? null;
                Log::info("Bluesky post published. URI: {$uri}");
                return [
                    'status'      => 'Success',
                    'response_id' => $uri,
                ];
            }

            $error = $response->json()['message'] ?? $response->body();
            return [
                'status'        => 'Failed',
                'error_message' => 'Bluesky API Error: ' . $error,
            ];

        } catch (\Exception $e) {
            Log::error("BlueskyService exception: " . $e->getMessage());
            return [
                'status'        => 'Failed',
                'error_message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }
}
