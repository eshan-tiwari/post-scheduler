<?php

namespace App\Services\Social;

class SocialServiceResolver
{
    /**
     * Resolve the service interface for the given platform.
     */
    public static function resolve(string $platform): PlatformServiceInterface
    {
        $platform = strtolower($platform);

        switch ($platform) {
            case 'twitter':
            case 'x':
            case 'x/twitter':
                return new TwitterService();
            case 'instagram':
                return new InstagramService();
            case 'facebook':
                return new FacebookService();
            case 'linkedin':
                return new LinkedInService();
            case 'bluesky':
                return new BlueskyService();
            case 'reddit':
                return new RedditService();
            default:
                throw new \InvalidArgumentException("Unsupported social platform: {$platform}");
        }
    }
}
