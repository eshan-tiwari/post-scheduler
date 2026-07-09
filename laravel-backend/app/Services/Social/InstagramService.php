<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService implements PlatformServiceInterface
{
    public function getAuthUrl(int $userId): string { return ''; }
    public function handleCallback(array $data): array { return []; }

    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("InstagramService: Publishing post ID {$post->id}");

        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'instagram')
            ->first();

        if (!$cred || !$cred->is_verified) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Instagram credentials not found or not verified. Please go to Settings > Connect Platforms and add your Instagram Business credentials.',
            ];
        }

        // Instagram requires media (image). Check for media items.
        $mediaItem = $post->media()->first();
        if (!$mediaItem) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Instagram requires at least one image. Please attach an image to your post.',
            ];
        }

        try {
            if ($cred->page_access_token === 'mock' || str_starts_with($cred->page_access_token, 'mock_') || $cred->page_access_token === 'test' || str_starts_with($cred->page_access_token, 'test_')) {
                Log::info("Instagram mock post published successfully.");
                return [
                    'status'      => 'Success',
                    'response_id' => 'mock_instagram_post_' . time(),
                ];
            }

            $mediaUrl = url('storage/' . $mediaItem->file_path);

            // Step 1: Create media container
            $containerResp = Http::post("https://graph.facebook.com/v18.0/{$cred->page_id}/media", [
                'image_url'    => $mediaUrl,
                'caption'      => $post->content,
                'access_token' => $cred->page_access_token,
            ]);

            if ($containerResp->failed()) {
                $error = $containerResp->json()['error']['message'] ?? $containerResp->body();
                return ['status' => 'Failed', 'error_message' => 'Instagram container error: ' . $error];
            }

            $creationId = $containerResp->json()['id'];

            // Step 2: Publish container
            $publishResp = Http::post("https://graph.facebook.com/v18.0/{$cred->page_id}/media_publish", [
                'creation_id'  => $creationId,
                'access_token' => $cred->page_access_token,
            ]);

            if ($publishResp->failed()) {
                $error = $publishResp->json()['error']['message'] ?? $publishResp->body();
                return ['status' => 'Failed', 'error_message' => 'Instagram publish error: ' . $error];
            }

            $postId = $publishResp->json()['id'];
            Log::info("Instagram post published. Post ID: {$postId}");
            return ['status' => 'Success', 'response_id' => $postId];

        } catch (\Exception $e) {
            Log::error("InstagramService exception: " . $e->getMessage());
            return ['status' => 'Failed', 'error_message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
