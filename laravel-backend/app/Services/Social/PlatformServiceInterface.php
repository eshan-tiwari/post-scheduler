<?php

namespace App\Services\Social;

use App\Models\ScheduledPost;
use App\Models\ConnectedAccount;

interface PlatformServiceInterface
{
    /**
     * Get the OAuth authorization URL.
     */
    public function getAuthUrl(int $userId): string;

    /**
     * Handle the callback authorization code and exchange it for tokens.
     * Returns an array with keys: platform_user_id, username, avatar_url, access_token, refresh_token, expires_at
     */
    public function handleCallback(array $data): array;

    /**
     * Publish the post content/media to the platform.
     * Returns an array with keys: status (Success/Failed), response_id (string, optional), error_message (string, optional)
     */
    public function publishPost(ScheduledPost $post, ConnectedAccount $account): array;
}
