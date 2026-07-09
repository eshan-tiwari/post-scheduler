<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookService implements PlatformServiceInterface
{
    public function getAuthUrl(int $userId): string { return ''; }
    public function handleCallback(array $data): array { return []; }

    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("FacebookService: Publishing post ID {$post->id}");

        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'facebook')
            ->first();

        if (!$cred || !$cred->is_verified) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Facebook credentials not found or not verified. Please go to Settings > Connect Platforms and add your Facebook Page credentials.',
            ];
        }

        try {
            if ($cred->page_access_token === 'mock' || str_starts_with($cred->page_access_token, 'mock_') || $cred->page_access_token === 'test' || str_starts_with($cred->page_access_token, 'test_')) {
                Log::info("Facebook mock post published successfully.");
                return [
                    'status'      => 'Success',
                    'response_id' => 'mock_fb_post_' . time(),
                ];
            }

            $mediaItems = $post->media;
            $pageId     = $cred->page_id;
            $token      = $cred->page_access_token;

            if ($mediaItems->isNotEmpty()) {
                if ($mediaItems->count() === 1) {
                    $mediaUrl = url('storage/' . $mediaItems->first()->file_path);
                    $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/photos", [
                        'url'          => $mediaUrl,
                        'message'      => $post->content,
                        'access_token' => $token,
                    ]);
                } else {
                    // Multi-photo post
                    $attachedMedia = [];
                    foreach ($mediaItems as $media) {
                        $photoUrl = url('storage/' . $media->file_path);
                        $photoRes = Http::post("https://graph.facebook.com/v18.0/{$pageId}/photos", [
                            'url'          => $photoUrl,
                            'published'    => false,
                            'access_token' => $token,
                        ]);
                        if ($photoRes->successful()) {
                            $attachedMedia[] = ['media_fbid' => $photoRes->json()['id']];
                        }
                    }
                    $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
                        'message'        => $post->content,
                        'attached_media' => $attachedMedia,
                        'access_token'   => $token,
                    ]);
                }
            } else {
                // Text-only post
                $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
                    'message'      => $post->content,
                    'access_token' => $token,
                ]);
            }

            if ($response->failed()) {
                $error = $response->json()['error']['message'] ?? $response->body();
                return ['status' => 'Failed', 'error_message' => 'Facebook API Error: ' . $error];
            }

            $postId = $response->json()['id'] ?? $response->json()['post_id'] ?? 'unknown';
            Log::info("Facebook post published. Post ID: {$postId}");
            return ['status' => 'Success', 'response_id' => $postId];

        } catch (\Exception $e) {
            Log::error("FacebookService exception: " . $e->getMessage());
            return ['status' => 'Failed', 'error_message' => 'Exception: ' . $e->getMessage()];
        }
    }
}
