<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The two mail palettes, kept in one place so a template cannot invent a third.
 *
 * `resources/views/emails/layout.blade.php` dresses the client space in coral,
 * `owner-layout.blade.php` dresses the landlord space in teal, and the shared
 * partials need the same tokens to stay in step with whichever layout wraps
 * them. Blade sections are captured before the layout renders, so a partial
 * cannot read a variable the layout defines — the audience has to be passed in
 * explicitly, and this class is what gets passed.
 *
 * `link` is deliberately darker than `accent`: the accent hues clear 3.6:1 on
 * white, which fails WCAG AA for text, so link text uses the darkened variant
 * both layouts already ship.
 *
 * @phpstan-type ThemeTokens array{
 *     audience: string,
 *     accent: string,
 *     link: string,
 *     gradient: string,
 *     surface: string,
 *     border: string,
 *     tintBg: string,
 *     tintBorder: string,
 *     tintText: string,
 * }
 */
final class MailTheme
{
    public const string CLIENT = 'client';

    public const string OWNER = 'owner';

    /**
     * Coral on slate — the visitor app.
     *
     * @return ThemeTokens
     */
    public static function client(): array
    {
        return [
            'audience' => self::CLIENT,
            'accent' => '#F6475F',
            'link' => '#C73B52',
            'gradient' => 'linear-gradient(135deg, #1e293b 0%, #C73B52 100%)',
            'surface' => '#f8fafc',
            'border' => '#e2e8f0',
            'tintBg' => '#fff1f2',
            'tintBorder' => '#fecdd3',
            'tintText' => '#9f1239',
        ];
    }

    /**
     * Teal on mint — the landlord panel.
     *
     * @return ThemeTokens
     */
    public static function owner(): array
    {
        return [
            'audience' => self::OWNER,
            'accent' => '#0d9488',
            'link' => '#0F766E',
            'gradient' => 'linear-gradient(135deg, #042f2e 0%, #0d9488 100%)',
            'surface' => '#f0fdfa',
            'border' => '#ccfbf1',
            'tintBg' => '#f0fdfa',
            'tintBorder' => '#99f6e4',
            'tintText' => '#115e59',
        ];
    }

    /**
     * @return ThemeTokens
     */
    public static function for(string $audience): array
    {
        return $audience === self::OWNER ? self::owner() : self::client();
    }
}
