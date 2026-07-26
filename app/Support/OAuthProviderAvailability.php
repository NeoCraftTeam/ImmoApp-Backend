<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether Laravel Socialite can initiate a browser redirect for a provider.
 * Empty client_id yields Google error « Missing required parameter: client_id ».
 */
final class OAuthProviderAvailability
{
    /** @var list<string> */
    private const array SOCIALITE_PROVIDERS = ['google', 'facebook', 'github', 'apple'];

    public static function isSocialiteConfigured(string $provider): bool
    {
        if (!in_array($provider, self::SOCIALITE_PROVIDERS, true)) {
            return false;
        }

        $clientId = config("services.{$provider}.client_id");

        return is_string($clientId) && $clientId !== '';
    }

    /**
     * @return array<string, bool>
     */
    public static function socialiteMap(): array
    {
        $map = [];
        foreach (self::SOCIALITE_PROVIDERS as $provider) {
            $map[$provider] = self::isSocialiteConfigured($provider);
        }

        return $map;
    }

    public static function isClerkConfigured(): bool
    {
        $key = config('clerk.publishable_key');

        return is_string($key) && $key !== '';
    }
}
