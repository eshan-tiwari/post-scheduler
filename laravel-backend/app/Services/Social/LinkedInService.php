<?php

namespace App\Services\Social;

use App\Models\PlatformCredential;
use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInService implements PlatformServiceInterface
{
    public function getAuthUrl(int $userId): string { return ''; }
    public function handleCallback(array $data): array { return []; }

    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array
    {
        Log::info("LinkedInService: Publishing post ID {$post->id}");

        $cred = PlatformCredential::where('user_id', $post->user_id)
            ->where('platform', 'linkedin')
            ->first();

        if (!$cred || !$cred->is_verified) {
            return [
                'status'        => 'Failed',
                'error_message' => 'LinkedIn credentials not found or not verified. Please go to Settings > Connect Platforms and add your LinkedIn credentials.',
            ];
        }

        try {
            if ($cred->li_access_token === 'mock' || str_starts_with($cred->li_access_token, 'mock_') || $cred->li_access_token === 'test' || str_starts_with($cred->li_access_token, 'test_')) {
                Log::info("LinkedIn mock post published successfully.");
                return [
                    'status'      => 'Success',
                    'response_id' => 'mock_linkedin_post_' . time(),
                ];
            }

            $authorUrn = $cred->li_person_urn; // e.g. urn:li:person:xxxxx
            $token     = $cred->li_access_token;

            $payload = [
                'author'         => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary'    => ['text' => $post->content],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            // Attach image if any
            $mediaItems = $post->media;
            if ($mediaItems->isNotEmpty()) {
                $assetUrn = $this->registerAndUploadMedia(
                    $mediaItems->first()->file_path,
                    $authorUrn,
                    $token
                );
                if ($assetUrn) {
                    $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                    $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [[
                        'status' => 'READY',
                        'media'  => $assetUrn,
                        'title'  => ['text' => $post->title ?: 'Post Image'],
                    ]];
                }
            }

            $response = Http::withToken($token)->post('https://api.linkedin.com/v2/ugcPosts', $payload);

            if ($response->failed()) {
                $error = $response->json()['message'] ?? $response->body();
                return ['status' => 'Failed', 'error_message' => 'LinkedIn API Error: ' . $error];
            }

            $postId = $response->json()['id'] ?? 'unknown';
            Log::info("LinkedIn post published. ID: {$postId}");
            return ['status' => 'Success', 'response_id' => $postId];

        } catch (\Exception $e) {
            Log::error("LinkedInService exception: " . $e->getMessage());
            return ['status' => 'Failed', 'error_message' => 'Exception: ' . $e->getMessage()];
        }
    }

    protected function registerAndUploadMedia(string $filePath, string $authorUrn, string $token): ?string
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            if (!file_exists($fullPath)) return null;

            $registerResponse = Http::withToken($token)->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                'registerRequest' => [
                    'recipes'                 => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner'                   => $authorUrn,
                    'supportedUploadMechanism'=> ['SYNCHRONOUS_UPLOAD'],
                ],
            ]);

            if ($registerResponse->failed()) return null;

            $uploadUrl = $registerResponse->json()['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn  = $registerResponse->json()['value']['asset'];

            Http::withBody(file_get_contents($fullPath), 'image/jpeg')->put($uploadUrl);
            return $assetUrn;

        } catch (\Exception $e) {
            Log::error("LinkedIn media upload failed: " . $e->getMessage());
            return null;
        }
    }
}
